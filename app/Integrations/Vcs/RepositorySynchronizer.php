<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryCommit;
use App\Models\RepositoryConnection;
use App\Models\RepositorySyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Provider-neutral writer for the new VCS tables.
 * In 3A.5.2a this is opt-in and is NOT yet called from existing GitHub routes.
 * Never run it concurrently with legacy sync before the controlled cutover.
 */
final class RepositorySynchronizer
{
    public function __construct(private readonly RepositoryHistoryProviders $providers) {}

    /** @return array{fetched:int,imported:int,head_sha:string} */
    public function synchronize(RepositoryConnection $repository): array
    {
        $lock = Cache::lock('vcs-repo-sync-'.$repository->id, 120);
        if (! $lock->get()) {
            throw new RuntimeException('Repository synchronization is already running.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $initialSha = $repository->last_synced_sha;
            $provider = $this->providers->forRepository($repository);
            $run = RepositorySyncRun::query()->create([
                'repository_connection_id' => $repository->id,
                'from_sha' => $initialSha,
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                $changes = $provider->changesSince($repository);
                $this->assertValidBatch($changes, $initialSha);
                $imported = DB::transaction(function () use ($repository, $initialSha, $changes, $run): int {
                    $fresh = RepositoryConnection::query()->lockForUpdate()->findOrFail($repository->id);
                    if ($fresh->last_synced_sha !== $initialSha) {
                        throw new RuntimeException('Repository cursor changed during synchronization.');
                    }

                    $before = RepositoryCommit::query()->where('repository_connection_id', $fresh->id)->count();
                    $rows = [];
                    $timestamp = now();
                    foreach ($changes['commits'] as $commit) {
                        $rows[] = [
                            'repository_connection_id' => $fresh->id,
                            'sha' => strtolower($commit['sha']),
                            'message' => $commit['message'],
                            'author_name' => $commit['author_name'],
                            'author_login' => $commit['author_login'],
                            'author_external_user_id' => $commit['author_external_user_id'],
                            'author_identity_checked_at' => $timestamp,
                            'committed_at' => $commit['committed_at'] !== null
                                ? Carbon::parse($commit['committed_at'])->utc()->toDateTimeString() : null,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                    foreach (array_chunk($rows, 100) as $chunk) {
                        RepositoryCommit::query()->insertOrIgnore($chunk);
                    }
                    $imported = RepositoryCommit::query()->where('repository_connection_id', $fresh->id)->count() - $before;

                    $fresh->last_synced_sha = strtolower($changes['head_sha']);
                    $fresh->last_synced_at = $timestamp;
                    $fresh->save();
                    $run->update([
                        'to_sha' => strtolower($changes['head_sha']),
                        'fetched_count' => count($changes['commits']),
                        'imported_count' => $imported,
                        'status' => 'completed',
                        'finished_at' => $timestamp,
                    ]);

                    return $imported;
                });

                return [
                    'fetched' => count($changes['commits']),
                    'imported' => $imported,
                    'head_sha' => strtolower($changes['head_sha']),
                ];
            } catch (Throwable $exception) {
                // Never persist untrusted GitHub HTTP bodies, credentials, or exception text.
                $run->update([
                    'status' => 'failed',
                    'error_message' => 'Repository synchronization failed. Retry later.',
                    'finished_at' => now(),
                ]);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /** @param array<string,mixed> $batch */
    private function assertValidBatch(array $batch, string $initialSha): void
    {
        if (! is_string($batch['head_sha'] ?? null)
            || ! preg_match('/\A[a-f0-9]{40}\z/iD', $batch['head_sha'])
            || ! is_array($batch['commits'] ?? null)
            || count($batch['commits']) > 500) {
            throw new RuntimeException('Provider returned invalid commit batch.');
        }
        if ($batch['commits'] === [] && strcasecmp($batch['head_sha'], $initialSha) !== 0) {
            throw new RuntimeException('Provider returned a new HEAD without its commits.');
        }
        foreach ($batch['commits'] as $commit) {
            if (! is_array($commit) || ! is_string($commit['sha'] ?? null)
                || ! preg_match('/\A[a-f0-9]{40}\z/iD', $commit['sha'])
                || ! is_string($commit['message'] ?? null)
                || mb_strlen($commit['message']) > 16000
                || ! array_key_exists('author_name', $commit)
                || ! array_key_exists('author_login', $commit)
                || ! array_key_exists('author_external_user_id', $commit)
                || ! array_key_exists('committed_at', $commit)
                || (isset($commit['author_name']) && ! is_string($commit['author_name']))
                || (isset($commit['author_login']) && ! is_string($commit['author_login']))
                || (isset($commit['committed_at']) && ! is_string($commit['committed_at']))
                || (isset($commit['author_external_user_id']) && (! is_string($commit['author_external_user_id']) || strlen($commit['author_external_user_id']) > 190))) {
                throw new RuntimeException('Provider returned invalid commit metadata.');
            }
        }
        if ($batch['commits'] !== [] && ! in_array(strtolower($batch['head_sha']), array_map(
            static fn (array $c): string => strtolower($c['sha']), $batch['commits'],
        ), true)) {
            throw new RuntimeException('Provider omitted the target HEAD commit.');
        }
    }
}
