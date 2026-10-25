<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Contracts\RepositoryCommitFileProvider;
use App\Integrations\Vcs\Providers\GitHubCommitFileProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

/** Add GitLab/Gitea adapters here, not in report generation. */
final class RepositoryCommitFileProviders
{
    public function __construct(private readonly GitHubCommitFileProvider $github) {}

    public function forRepository(RepositoryConnection $repository): RepositoryCommitFileProvider
    {
        $repository->loadMissing('connection.instance');

        return match ($repository->connection?->instance?->provider) {
            'github' => $this->github,
            default => throw new RuntimeException('Commit file metadata is unavailable for this VCS provider.'),
        };
    }
}
