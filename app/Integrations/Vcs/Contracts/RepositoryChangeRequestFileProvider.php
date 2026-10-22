<?php

namespace App\Integrations\Vcs\Contracts;

use App\Models\RepositoryConnection;

/** Provider-neutral, bounded file snapshots for already stored PRs/MRs. */
interface RepositoryChangeRequestFileProvider
{
    /**
     * @param list<array{number:int,head_sha:string}> $pulls
     * @return array<int, list<array<string, mixed>>> Normalized files indexed by PR/MR number
     */
    public function filesForStoredRequests(RepositoryConnection $repository, array $pulls): array;
}
