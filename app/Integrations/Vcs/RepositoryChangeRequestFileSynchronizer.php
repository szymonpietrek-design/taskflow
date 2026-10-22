<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Full-file snapshots are atomic, revision-bound, and never advance the commit cursor. */
final class RepositoryChangeRequestFileSynchronizer
{
    private const BATCH_SIZE = 5;

    public function __construct(private readonly RepositoryChangeRequestFileProviders $providers) {}

    /** @return array{checked:int,files:int,remaining:int} */
    public function synchronize(RepositoryConnection $repository, bool $force = false, ?int $pullNumber = null): array
    {
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 600);
        if (! $lock->get()) {
            throw new RuntimeException('A repository operation is already in progress.');
        }
        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $eligible = fn () => RepositoryChangeRequest::query()
                ->where('repository_connection_id', $repository->id)
                ->whereNotNull('head_sha')
                ->when($pullNumber !== null, fn ($q) => $q->where('number', $pullNumber))
                ->when(! $force, fn ($query) => $query->where(function ($q): void {
                    $q->whereNull('files_synced_at')
                        ->orWhereNull('files_head_sha')
                        ->orWhereColumn('files_head_sha', '!=', 'head_sha');
                }));
            $requests = $eligible()->orderBy('files_synced_at')->orderBy('id')
                ->limit(self::BATCH_SIZE)->get(['id', 'number', 'head_sha']);
            if ($requests->isEmpty()) {
                return ['checked' => 0, 'files' => 0, 'remaining' => 0];
            }
            $pulls = $requests->map(fn ($pull): array => [
                'number' => (int) $pull->number, 'head_sha' => (string) $pull->head_sha,
            ])->all();
            $snapshots = $this->providers->forRepository($repository)->filesForStoredRequests($repository, $pulls);
            if (! is_array($snapshots) || count($snapshots) !== count($pulls)) {
                throw new RuntimeException('Incomplete provider file snapshots.');
            }
            $count = 0;
            foreach ($pulls as $pull) {
                $files = $snapshots[$pull['number']] ?? null;
                if (! is_array($files) || ! array_is_list($files) || count($files) > 500) {
                    throw new RuntimeException('Invalid provider file list.');
                }
                $hashes = [];
                foreach ($files as $file) {
                    $this->validate($file);
                    if (isset($hashes[$file['path_hash']])) {
                        throw new RuntimeException('Duplicate file path in PR snapshot.');
                    }
                    $hashes[$file['path_hash']] = true;
                }
                $count += count($files);
            }
            DB::transaction(function () use ($repository, $requests, $snapshots): void {
                RepositoryConnection::query()->whereKey($repository->id)->lockForUpdate()->firstOrFail();
                foreach ($requests as $request) {
                    $stored = RepositoryChangeRequest::query()
                        ->where('repository_connection_id', $repository->id)
                        ->whereKey($request->id)->lockForUpdate()->firstOrFail();
                    if ($stored->head_sha !== $request->head_sha) {
                        throw new RuntimeException('PR HEAD changed while collecting files. Retry after syncing PR metadata.');
                    }
                    $stored->files()->delete();
                    foreach ($snapshots[$request->number] as $file) {
                        $stored->files()->create($file);
                    }
                    $stored->forceFill([
                        'files_head_sha' => $request->head_sha,
                        'files_synced_at' => now(),
                    ])->save();
                }
            });
            return [
                'checked' => $requests->count(),
                'files' => $count,
                'remaining' => $force
                    ? max(0, $eligible()->count() - $requests->count())
                    : $eligible()->count(),
            ];
        } finally {
            $lock->release();
        }
    }

    private function validate(mixed $file): void
    {
        if (! is_array($file) || ! is_string($file['path'] ?? null)
            || $file['path'] === '' || mb_strlen($file['path']) > 1024
            || ! mb_check_encoding($file['path'], 'UTF-8')
            || str_contains($file['path'], "\0")
            || preg_match('/[\r\n]/', $file['path'])
            || ! is_string($file['path_hash'] ?? null)
            || $file['path_hash'] !== hash('sha256', $file['path'])
            || ! in_array($file['status'] ?? null, [
                'added', 'modified', 'removed', 'renamed', 'copied', 'changed', 'unchanged',
            ], true)) {
            throw new RuntimeException('Invalid normalized file snapshot.');
        }
        foreach (['additions', 'deletions', 'changes'] as $field) {
            if (! is_int($file[$field] ?? null) || $file[$field] < 0 || $file[$field] > 1000000000) {
                throw new RuntimeException('Invalid normalized changed lines count.');
            }
        }
        foreach (['previous_path' => 1024, 'blob_sha' => 64, 'patch' => 12000] as $key => $max) {
            if (! array_key_exists($key, $file) || ($file[$key] !== null
                && (! is_string($file[$key]) || strlen($file[$key]) > $max))) {
                throw new RuntimeException('Invalid normalized file '.$key.'.');
            }
        }
        if (! array_key_exists('patch_truncated', $file) || ! is_bool($file['patch_truncated'])
            || ($file['patch_truncated'] && $file['patch'] !== null)) {
            throw new RuntimeException('Invalid file patch truncation metadata.');
        }
    }
}
