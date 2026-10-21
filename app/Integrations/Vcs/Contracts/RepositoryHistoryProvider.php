<?php

namespace App\Integrations\Vcs\Contracts;

use App\Models\RepositoryConnection;

/**
 * Reads a provider-specific Git history into a provider-neutral shape.
 * This contract does not decide permissions, update DB cursors or import history.
 */
interface RepositoryHistoryProvider
{
    /**
     * @return array{head_sha: string, commits: list<array{
     *     sha: string, message: string, author_name: ?string, author_login: ?string,
     *     author_external_user_id: ?string, committed_at: ?string
     * }>}
     */
    public function changesSince(RepositoryConnection $repository): array;

    /**
     * Only inspect SHAs already stored in TaskFlow; do not list older history.
     *
     * @param list<string> $shas Maximum ten SHAs per call.
     * @return array<string, ?string> SHA => verified provider user ID, or null.
     */
    public function authorIdentitiesForStoredCommits(RepositoryConnection $repository, array $shas): array;
}
