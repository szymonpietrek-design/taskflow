<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Contracts\RepositoryChangeRequestFileProvider;
use App\Integrations\Vcs\Providers\GitHubPullRequestFileProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryChangeRequestFileProviders
{
    public function __construct(private readonly GitHubPullRequestFileProvider $github) {}

    public function forRepository(RepositoryConnection $repository): RepositoryChangeRequestFileProvider
    {
        $repository->loadMissing('connection.instance');
        return match ($repository->connection?->instance?->provider) {
            'github' => $this->github,
            default => throw new RuntimeException('File changes are unavailable for this VCS provider.'),
        };
    }
}
