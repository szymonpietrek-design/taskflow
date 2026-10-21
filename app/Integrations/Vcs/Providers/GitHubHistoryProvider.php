<?php

namespace App\Integrations\Vcs\Providers;

use App\Integrations\GitHub\GitHubCommitReader;
use App\Integrations\Vcs\Contracts\RepositoryHistoryProvider;
use App\Models\RepositoryConnection;
use RuntimeException;

/** GitHub adapter: translates GitHub IDs, without changing the proven API reader. */
final class GitHubHistoryProvider implements RepositoryHistoryProvider
{
    public function __construct(private readonly GitHubCommitReader $reader) {}

    public function changesSince(RepositoryConnection $repository): array
    {
        [$installationId, $repositoryId] = $this->identifiers($repository);
        $result = $this->reader->changesSince(
            $installationId,
            $repository->full_name,
            $repository->default_branch,
            $repository->last_synced_sha,
            $repositoryId,
        );

        return [
            'head_sha' => $result['head_sha'],
            'commits' => array_map(static fn (array $commit): array => [
                'sha' => $commit['sha'],
                'message' => $commit['message'],
                'author_name' => $commit['author_name'],
                'author_login' => $commit['author_login'],
                // Login or name is never used as a substitute for verified ID.
                'author_external_user_id' => isset($commit['author_github_user_id'])
                    ? (string) $commit['author_github_user_id'] : null,
                'committed_at' => $commit['committed_at'],
            ], $result['commits']),
        ];
    }

    public function authorIdentitiesForStoredCommits(RepositoryConnection $repository, array $shas): array
    {
        [$installationId, $repositoryId] = $this->identifiers($repository);
        if (count($shas) > 10) {
            throw new RuntimeException('The author lookup batch exceeds the limit.');
        }

        $identities = $this->reader->authorIdentitiesForStoredCommits(
            $installationId, $repository->full_name, $repositoryId, $shas,
        );

        return array_map(static fn (?int $id): ?string => $id === null ? null : (string) $id, $identities);
    }

    /** @return array{int,int} */
    private function identifiers(RepositoryConnection $repository): array
    {
        $repository->loadMissing('connection.instance');
        $connection = $repository->connection;
        $instance = $connection?->instance;
        if ($instance?->provider !== 'github'
            || $instance->base_url !== 'https://github.com'
            || $instance->instance_key !== hash('sha256', 'https://github.com')) {
            // Do not send GitHub App credentials to an untrusted/self-hosted host.
            throw new RuntimeException('Unsupported GitHub provider instance.');
        }

        return [
            self::positiveInteger($connection->external_connection_id),
            self::positiveInteger($repository->external_repository_id),
        ];
    }

    private static function positiveInteger(string $value): int
    {
        if (! ctype_digit($value) || (int) $value < 1 || (string) (int) $value !== $value) {
            throw new RuntimeException('Invalid external GitHub identifier.');
        }

        return (int) $value;
    }
}
