<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryCommit;
use App\Models\RepositoryConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Safely resolve identities for known commits only; never import older history. */
final class RepositoryAuthorBackfiller
{
    private const BATCH_SIZE = 10;

    public function __construct(private readonly RepositoryHistoryProviders $providers) {}

    /** @return array{checked:int,remaining:int} */
    public function backfill(RepositoryConnection $repository): array
    {
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        if (! $lock->get()) {
            throw new RuntimeException('Repository synchronization is already running.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $provider = $this->providers->forRepository($repository);
            $commits = RepositoryCommit::query()
                ->where('repository_connection_id', $repository->id)
                ->whereNull('author_identity_checked_at')
                ->orderBy('id')->limit(self::BATCH_SIZE)->get(['id', 'sha']);

            if ($commits->isEmpty()) {
                return ['checked' => 0, 'remaining' => 0];
            }

            $identities = $provider->authorIdentitiesForStoredCommits($repository, $commits->pluck('sha')->all());
            foreach ($commits as $commit) {
                if (! array_key_exists(strtolower($commit->sha), $identities)) {
                    throw new RuntimeException('Incomplete provider author lookup.');
                }
                $id = $identities[strtolower($commit->sha)];
                if ($id !== null && (! is_string($id) || $id === '' || strlen($id) > 190)) {
                    throw new RuntimeException('Invalid external author ID.');
                }
            }

            DB::transaction(function () use ($commits, $identities): void {
                foreach ($commits as $commit) {
                    RepositoryCommit::query()->whereKey($commit->id)
                        ->whereNull('author_identity_checked_at')
                        ->update([
                            'author_external_user_id' => $identities[strtolower($commit->sha)],
                            'author_identity_checked_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            });

            return [
                'checked' => $commits->count(),
                'remaining' => RepositoryCommit::query()
                    ->where('repository_connection_id', $repository->id)
                    ->whereNull('author_identity_checked_at')->count(),
            ];
        } finally {
            $lock->release();
        }
    }
}
