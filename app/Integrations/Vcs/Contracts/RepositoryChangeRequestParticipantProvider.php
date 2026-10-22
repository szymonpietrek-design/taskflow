<?php

namespace App\Integrations\Vcs\Contracts;

use App\Models\RepositoryConnection;

/** Reads the merging identity for PRs already stored by the repository synchronizer. */
interface RepositoryChangeRequestParticipantProvider
{
    /**
     * @param list<int> $numbers Repository-scoped PR/MR numbers, never arbitrary URLs.
     * @return array<int, array{external_user_id:?string,login:?string}>
     */
    public function mergedByForStoredRequests(RepositoryConnection $repository, array $numbers): array;
}
