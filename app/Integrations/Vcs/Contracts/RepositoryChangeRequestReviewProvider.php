<?php

namespace App\Integrations\Vcs\Contracts;

use App\Models\RepositoryConnection;

/** Read complete bounded review histories for already imported PRs / MRs. */
interface RepositoryChangeRequestReviewProvider
{
    /**
     * @param list<int> $numbers Repository-local PR/MR numbers
     * @return array<int, list<array<string, mixed>>> Normalized review snapshots keyed by PR number
     */
    public function reviewsForStoredRequests(RepositoryConnection $repository, array $numbers): array;
}
