<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Contracts\RepositoryHistoryProvider;
use App\Integrations\Vcs\Providers\GitHubHistoryProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

/** Explicit allow-list. New providers get their own adapters and security validation. */
final class RepositoryHistoryProviders
{
    public function __construct(private readonly GitHubHistoryProvider $github) {}

    public function forRepository(RepositoryConnection $repository): RepositoryHistoryProvider
    {
        $repository->loadMissing('connection.instance');

        return match ($repository->connection?->instance?->provider) {
            'github' => $this->github,
            default => throw new RuntimeException('Unsupported VCS provider.'),
        };
    }
}
