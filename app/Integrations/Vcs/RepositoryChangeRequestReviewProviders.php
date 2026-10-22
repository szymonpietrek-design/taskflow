<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Contracts\RepositoryChangeRequestReviewProvider;
use App\Integrations\Vcs\Providers\GitHubPullRequestReviewProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryChangeRequestReviewProviders
{
    public function __construct(private readonly GitHubPullRequestReviewProvider $github) {}

    public function forRepository(RepositoryConnection $repository): RepositoryChangeRequestReviewProvider
    {
        $repository->loadMissing('connection.instance');

        return match ($repository->connection?->instance?->provider) {
            'github' => $this->github,
            default => throw new RuntimeException('PR/MR review synchronization is unavailable for this provider.'),
        };
    }
}
