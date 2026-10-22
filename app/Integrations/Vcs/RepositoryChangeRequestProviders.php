<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Contracts\RepositoryChangeRequestProvider;
use App\Integrations\Vcs\Providers\GitHubPullRequestProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryChangeRequestProviders
{
    public function __construct(private readonly GitHubPullRequestProvider $github) {}

    public function forRepository(RepositoryConnection $repository): RepositoryChangeRequestProvider
    {
        $repository->loadMissing('connection.instance');

        return match ($repository->connection?->instance?->provider) {
            'github' => $this->github,
            default => throw new RuntimeException('Pull request synchronization is not available for this VCS provider.'),
        };
    }
}
