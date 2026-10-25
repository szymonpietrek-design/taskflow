<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryCommit;
use App\Models\RepositoryConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Bounded enrichment; does not move repository cursors and never saves code patches. */
final class RepositoryCommitFileSynchronizer
{
    private const BATCH_SIZE = 5;

    public function __construct(private readonly RepositoryCommitFileProviders $providers) {}

    /** @return array{checked:int,files:int,remaining:int} */
    public function synchronize(RepositoryConnection $repository): array
    {
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 600);
        if (! $lock->get()) {
            throw new RuntimeException('A repository operation is already in progress.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $pending = fn () => RepositoryCommit::query()
                ->where('repository_connection_id', $repository->id)
                ->whereNull('files_synced_at');
            $commits = $pending()->orderBy('id')->limit(self::BATCH_SIZE)->get(['id', 'sha']);
            if ($commits->isEmpty()) {
                return ['checked' => 0, 'files' => 0, 'remaining' => 0];
            }
            $shas = $commits->pluck('sha')->all();
            $snapshots = $this->providers->forRepository($repository)->filesForStoredCommits($repository, $shas);
            if (! is_array($snapshots) || count($snapshots) !== count($shas)) {
                throw new RuntimeException('Incomplete commit file snapshots.');
            }

            $total = 0;
            foreach ($shas as $sha) {
                $files = $snapshots[$sha] ?? null;
                if (! is_array($files) || ! array_is_list($files) || count($files) >= 300) {
                    throw new RuntimeException('Invalid or truncated commit file list.');
                }
                $paths = [];
                foreach ($files as $file) {
                    $this->validate($file);
                    if (isset($paths[$file['path_hash']])) {
                        throw new RuntimeException('Duplicate changed file in commit.');
                    }
                    $paths[$file['path_hash']] = true;
                }
                $total += count($files);
            }

            DB::transaction(function () use ($repository, $commits, $snapshots): void {
                RepositoryConnection::query()->whereKey($repository->id)->lockForUpdate()->firstOrFail();
                foreach ($commits as $commit) {
                    $stored = RepositoryCommit::query()
                        ->where('repository_connection_id', $repository->id)
                        ->whereKey($commit->id)->lockForUpdate()->firstOrFail();
                    if (! hash_equals($stored->sha, $commit->sha)) {
                        throw new RuntimeException('Commit changed while collecting file metadata.');
                    }
                    // Another completed snapshot must not be overwritten by an older request.
                    if ($stored->files_synced_at !== null) {
                        throw new RuntimeException('Commit file snapshot was synchronized concurrently.');
                    }
                    foreach ($snapshots[$commit->sha] as $file) {
                        $stored->files()->create($file);
                    }
                    $stored->forceFill([
                        'files_count' => count($snapshots[$commit->sha]),
                        'files_synced_at' => now(),
                    ])->save();
                }
            });

            return [
                'checked' => $commits->count(),
                'files' => $total,
                'remaining' => $pending()->count(),
            ];
        } finally {
            $lock->release();
        }
    }

    private function validate(mixed $file): void
    {
        if (! is_array($file) || count($file) !== 7
            || ! is_string($file['path'] ?? null) || $file['path'] === ''
            || mb_strlen($file['path']) > 1024 || ! mb_check_encoding($file['path'], 'UTF-8')
            || str_contains($file['path'], "\0") || preg_match('/[\r\n]/', $file['path'])
            || str_starts_with($file['path'], '/') || preg_match('~(^|/)\.\.(/|$)~', $file['path'])
            || ! is_string($file['path_hash'] ?? null)
            || $file['path_hash'] !== hash('sha256', $file['path'])
            || ! in_array($file['status'] ?? null, ['added', 'modified', 'removed', 'renamed', 'copied', 'changed', 'unchanged'], true)
            || ! array_key_exists('previous_path', $file)
            || ($file['previous_path'] !== null && (! is_string($file['previous_path'])
                || mb_strlen($file['previous_path']) > 1024 || ! mb_check_encoding($file['previous_path'], 'UTF-8')
                || str_contains($file['previous_path'], "\0") || preg_match('/[\r\n]/', $file['previous_path'])
                || str_starts_with($file['previous_path'], '/') || preg_match('~(^|/)\.\.(/|$)~', $file['previous_path'])))
            || ($file['status'] === 'renamed' && $file['previous_path'] === null)) {
            throw new RuntimeException('Invalid normalized commit file metadata.');
        }
        foreach (['additions', 'deletions', 'changes'] as $key) {
            if (! is_int($file[$key] ?? null) || $file[$key] < 0 || $file[$key] > 1000000000) {
                throw new RuntimeException('Invalid normalized commit file line counts.');
            }
        }
    }
}
