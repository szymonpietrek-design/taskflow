<?php

namespace App\Integrations\GitHub;

use App\Models\GitHubCommit;
use App\Models\GitHubRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves authors for commits imported before GitHub numeric IDs were stored.
 * This never imports new commit history; it only refreshes known SHAs.
 */
class GitHubAuthorBackfiller
{
    private const BATCH_SIZE = 10;

    public function __construct(private readonly GitHubCommitReader $reader) {}

    /** @return array{checked:int,remaining:int} */
    public function backfill(GitHubRepository $repository): array
    {
        // Do not race with the ordinary cursor-based synchronization.
        $lock = Cache::lock('github-repo-sync-'.$repository->id, 120);
        if (! $lock->get()) {
            throw new RuntimeException('GitHub synchronization is already running for this repository.');
        }

        try {
            $repository->refresh()->load('installation');
            $commits = GitHubCommit::query()
                ->where('github_repository_id', $repository->id)
                ->whereNull('author_identity_checked_at')
                ->orderBy('id')->limit(self::BATCH_SIZE)->get(['id', 'sha']);

            if ($commits->isEmpty()) {
                return ['checked' => 0, 'remaining' => 0];
            }

            $identities = $this->reader->authorIdentitiesForStoredCommits(
                $repository->installation->installation_id,
                $repository->full_name,
                $repository->github_repository_id,
                $commits->pluck('sha')->all(),
            );

            foreach ($commits as $commit) {
                if (! array_key_exists(strtolower($commit->sha), $identities)) {
                    throw new RuntimeException('Incomplete GitHub author lookup response.');
                }
            }

            // Only commit a complete batch, including explicit GitHub "unknown author" results.
            DB::transaction(function () use ($commits, $identities): void {
                foreach ($commits as $commit) {
                    GitHubCommit::query()->whereKey($commit->id)
                        ->whereNull('author_identity_checked_at')
                        ->update([
                            'author_github_user_id' => $identities[strtolower($commit->sha)],
                            'author_identity_checked_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            });

            return [
                'checked' => $commits->count(),
                'remaining' => GitHubCommit::query()->where('github_repository_id', $repository->id)
                    ->whereNull('author_identity_checked_at')->count(),
            ];
        } finally {
            $lock->release();
        }
    }
}
