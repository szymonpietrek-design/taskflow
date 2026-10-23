<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryConnection;
use RuntimeException;

/** Explicit one-PR refresh: remote PR metadata first, then files bound to its HEAD. */
final class RepositoryChangeRequestAiSnapshotSynchronizer
{
    public function __construct(
        private readonly RepositoryChangeRequestSynchronizer $pulls,
        private readonly RepositoryChangeRequestFileSynchronizer $files,
    ) {}

    /** @return array{files:int,head_sha:string} */
    public function synchronize(RepositoryConnection $repository, int $number): array
    {
        $this->pulls->refreshOne($repository, $number);
        $result = $this->files->synchronize($repository, force: true, pullNumber: $number);

        $pull = $repository->changeRequests()->where('number', $number)->firstOrFail();
        if ($result['checked'] !== 1 || $pull->head_sha === null || $pull->synced_at === null
            || $pull->synced_at->lt(now()->subMinutes(30))
            || $pull->files_synced_at === null || $pull->files_synced_at->lt(now()->subMinutes(30))
            || $pull->files_head_sha !== $pull->head_sha) {
            throw new RuntimeException('Pull request and file snapshots are not ready for analysis.');
        }

        return ['files' => (int) $result['files'], 'head_sha' => $pull->head_sha];
    }
}
