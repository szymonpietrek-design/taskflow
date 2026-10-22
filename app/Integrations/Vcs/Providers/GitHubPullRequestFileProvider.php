<?php

namespace App\Integrations\Vcs\Providers;

use App\Integrations\GitHub\GitHubPullRequestFileReader;
use App\Integrations\Vcs\Contracts\RepositoryChangeRequestFileProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

final class GitHubPullRequestFileProvider implements RepositoryChangeRequestFileProvider
{
    public function __construct(private readonly GitHubPullRequestFileReader $reader) {}

    public function filesForStoredRequests(RepositoryConnection $repository, array $pulls): array
    {
        $repository->loadMissing('connection.instance');
        $instance = $repository->connection?->instance;
        if ($instance?->provider !== 'github'
            || $instance->instance_key !== GitHubInstance::key()
            || $instance->base_url !== GitHubInstance::BASE_URL) {
            throw new RuntimeException('Unsupported GitHub instance for PR file synchronization.');
        }
        return $this->reader->filesForStoredPullRequests(
            $this->positiveId($repository->connection->external_connection_id),
            $repository->full_name,
            $this->positiveId($repository->external_repository_id),
            $pulls,
        );
    }

    private function positiveId(mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value)
            || (int) $value < 1 || (string) (int) $value !== (string) $value) {
            throw new RuntimeException('Invalid GitHub numeric identifier.');
        }
        return (int) $value;
    }
}
