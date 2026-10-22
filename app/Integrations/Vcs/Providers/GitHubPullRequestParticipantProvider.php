<?php

namespace App\Integrations\Vcs\Providers;

use App\Integrations\GitHub\GitHubPullRequestReader;
use App\Integrations\Vcs\Contracts\RepositoryChangeRequestParticipantProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

final class GitHubPullRequestParticipantProvider implements RepositoryChangeRequestParticipantProvider
{
    public function __construct(private readonly GitHubPullRequestReader $reader) {}

    public function mergedByForStoredRequests(RepositoryConnection $repository, array $numbers): array
    {
        $repository->loadMissing('connection.instance');
        $instance = $repository->connection?->instance;
        if ($instance?->provider !== 'github'
            || $instance->base_url !== GitHubInstance::BASE_URL
            || $instance->instance_key !== GitHubInstance::key()) {
            throw new RuntimeException('Unsupported GitHub instance for pull request participants.');
        }

        $installationId = $this->positiveId($repository->connection->external_connection_id);
        $repositoryId = $this->positiveId($repository->external_repository_id);
        return $this->reader->mergedByForStoredPullRequests($installationId, $repository->full_name, $repositoryId, $numbers);
    }

    private function positiveId(mixed $value): int
    {
        if ((! is_string($value) && ! is_int($value)) || ! ctype_digit((string) $value)
            || (int) $value < 1 || (string) (int) $value !== (string) $value) {
            throw new RuntimeException('Invalid GitHub numeric identifier.');
        }
        return (int) $value;
    }
}
