<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Contracts\RepositoryChangeRequestParticipantProvider;
use App\Integrations\Vcs\Providers\GitHubPullRequestParticipantProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryChangeRequestParticipantProviders
{
    public function __construct(private readonly GitHubPullRequestParticipantProvider $github) {}

    public function forRepository(RepositoryConnection $repository): RepositoryChangeRequestParticipantProvider
    {
        $repository->loadMissing('connection.instance');

        return match ($repository->connection?->instance?->provider) {
            'github' => $this->github,
            default => throw new RuntimeException('PR/MR participant lookup is not available for this VCS provider.'),
        };
    }
}
