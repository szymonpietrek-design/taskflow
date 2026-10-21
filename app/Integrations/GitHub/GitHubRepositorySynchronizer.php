<?php

namespace App\Integrations\GitHub;

use App\Models\GitHubCommit;
use App\Models\GitHubRepository;
use App\Models\GitHubSyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class GitHubRepositorySynchronizer
{
    public function __construct(private readonly GitHubCommitReader $reader) {}

    /**
     * @return array{fetched: int, imported: int, head_sha: string}
     */
    public function synchronize(GitHubRepository $repository): array
    {
        $lock = Cache::lock('github-repo-sync-'.$repository->id, 120);
        if (! $lock->get()) {
            throw new RuntimeException('Synchronization is already running for this repository.');
        }

        try {
            $repository->refresh()->load('installation');
            $initialSha = $repository->last_synced_sha;
            $run = GitHubSyncRun::query()->create([
                'github_repository_id' => $repository->id,
                'from_sha' => $initialSha,
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                $changes = $this->reader->changesSince(
                    $repository->installation->installation_id,
                    $repository->full_name,
                    $repository->default_branch,
                    $initialSha,
                    $repository->github_repository_id,
                );

                // Save the full validated batch and cursor in one transaction.
                // Failure leaves the old SHA intact and allows a retry.
                $imported = DB::transaction(function () use ($repository, $initialSha, $changes, $run): int {
                    $fresh = GitHubRepository::query()->lockForUpdate()->findOrFail($repository->id);
                    if ($fresh->last_synced_sha !== $initialSha) {
                        throw new RuntimeException('Repository cursor changed during synchronization.');
                    }
                    $before = GitHubCommit::query()->where('github_repository_id', $fresh->id)->count();
                    $rows = [];
                    foreach ($changes['commits'] as $commit) {
                        $rows[] = [
                            'github_repository_id' => $fresh->id,
                            'sha' => $commit['sha'],
                            'message' => $commit['message'],
                            'author_name' => $commit['author_name'],
                            'author_login' => $commit['author_login'],
                            'committed_at' => $commit['committed_at'] !== null
                                ? Carbon::parse($commit['committed_at'])->utc()->toDateTimeString()
                                : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    foreach (array_chunk($rows, 100) as $chunk) {
                        GitHubCommit::query()->insertOrIgnore($chunk);
                    }
                    $imported = GitHubCommit::query()->where('github_repository_id', $fresh->id)->count() - $before;
                    $fresh->last_synced_sha = $changes['head_sha'];
                    $fresh->last_synced_at = now();
                    $fresh->save();
                    $run->update([
                        'to_sha' => $changes['head_sha'],
                        'fetched_count' => count($changes['commits']),
                        'imported_count' => $imported,
                        'status' => 'completed',
                        'finished_at' => now(),
                    ]);
                    return $imported;
                });

                return [
                    'fetched' => count($changes['commits']),
                    'imported' => $imported,
                    'head_sha' => $changes['head_sha'],
                ];
            } catch (Throwable $exception) {
                // Never log or persist request headers / tokens or untrusted error bodies.
                $run->update([
                    'status' => 'failed',
                    'error_message' => $exception instanceof RuntimeException
                        ? Str::limit($exception->getMessage(), 250)
                        : 'GitHub request or synchronization failed. Retry later.',
                    'finished_at' => now(),
                ]);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }
}
