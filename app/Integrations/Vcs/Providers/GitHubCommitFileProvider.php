<?php

namespace App\Integrations\Vcs\Providers;

use App\Integrations\GitHub\GitHubCommitFileReader;
use App\Integrations\Vcs\Contracts\RepositoryCommitFileProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

/** GitHub-specific transport; report storage stays provider-neutral. */
final class GitHubCommitFileProvider implements RepositoryCommitFileProvider
{
    public function __construct(private readonly GitHubCommitFileReader $reader) {}

    public function filesForStoredCommits(RepositoryConnection $repository, array $shas): array
    {
        $repository->loadMissing('connection.instance');
        $connection = $repository->connection;
        $instance = $connection?->instance;
        if ($instance?->provider !== 'github'
            || $instance->base_url !== 'https://github.com'
            || $instance->instance_key !== hash('sha256', 'https://github.com')) {
            throw new RuntimeException('Unsupported GitHub provider instance.');
        }

        foreach ([$connection->external_connection_id, $repository->external_repository_id] as $id) {
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1
                || (string) (int) $id !== $id) {
                throw new RuntimeException('Invalid external GitHub identifier.');
            }
        }

        return $this->reader->filesForStoredCommits(
            (int) $connection->external_connection_id,
            $repository->full_name,
            (int) $repository->external_repository_id,
            $shas,
        );
    }
}
