<?php

namespace App\Integrations\Vcs\Providers;

use App\Integrations\GitHub\GitHubPullRequestReader;
use App\Integrations\Vcs\Contracts\RepositoryChangeRequestProvider;
use App\Models\RepositoryConnection;
use Illuminate\Support\Carbon;
use RuntimeException;

/** Converts GitHub PR snapshots to the provider-neutral VCS schema. */
final class GitHubPullRequestProvider implements RepositoryChangeRequestProvider
{
    public function __construct(private readonly GitHubPullRequestReader $reader) {}

    public function updatedSince(RepositoryConnection $repository, ?Carbon $since): array
    {
        $repository->loadMissing('connection.instance');
        $instance = $repository->connection?->instance;
        if ($instance?->provider !== 'github'
            || $instance->base_url !== GitHubInstance::BASE_URL
            || $instance->instance_key !== GitHubInstance::key()) {
            throw new RuntimeException('Unsupported GitHub instance for pull requests.');
        }

        $installationId = $this->positiveId($repository->connection->external_connection_id);
        $repositoryId = $this->positiveId($repository->external_repository_id);
        $items = $this->reader->listPullRequests($installationId, $repository->full_name, $repositoryId, $since);

        return array_map(fn (array $item): array => $this->normalize($item, $repository->full_name), $items);
    }

    private function normalize(array $item, string $repositoryName): array
    {
        $id = $this->positiveId($item['id'] ?? null);
        $number = $this->positiveId($item['number'] ?? null);
        $title = $this->text($item['title'] ?? null, 500, false);
        $base = $item['base'] ?? null;
        $head = $item['head'] ?? null;
        if (! is_array($head) || ! is_array($base)
            || ! in_array($item['state'] ?? null, ['open', 'closed'], true)) {
            throw new RuntimeException('Invalid GitHub pull request fields.');
        }
        $mergedAt = $this->date($item['merged_at'] ?? null);
        if ($mergedAt !== null && $item['state'] !== 'closed') {
            throw new RuntimeException('Inconsistent GitHub merged pull request state.');
        }
        // Always construct the link ourselves; never follow a URL supplied by a provider.
        $url = 'https://github.com/'.$repositoryName.'/pull/'.$number;

        return [
            'external_change_request_id' => (string) $id,
            'number' => $number,
            'title' => $title,
            'body' => $this->text($item['body'] ?? null, 65535, true),
            'web_url' => $url,
            'state' => $mergedAt !== null ? 'merged' : $item['state'],
            'is_draft' => ($item['draft'] ?? false) === true,
            'is_locked' => ($item['locked'] ?? false) === true,
            'source_branch' => $this->text($head['ref'] ?? null, 255, true),
            'target_branch' => $this->text($base['ref'] ?? null, 255, true),
            'source_repository' => $this->text($head['repo']['full_name'] ?? null, 255, true),
            'head_sha' => $this->sha($head['sha'] ?? null),
            'base_sha' => $this->sha($base['sha'] ?? null),
            'merge_commit_sha' => $this->sha($item['merge_commit_sha'] ?? null),
            'author_external_user_id' => $this->externalUserId($item['user']['id'] ?? null),
            'author_login' => $this->text($item['user']['login'] ?? null, 190, true),
            // Merged-by requires a detail request (stage 3B.3). Do not overwrite it with null.
            'remote_created_at' => $this->date($item['created_at'] ?? null, false),
            'remote_updated_at' => $this->date($item['updated_at'] ?? null, false),
            'closed_at' => $this->date($item['closed_at'] ?? null),
            'merged_at' => $mergedAt,
        ];
    }

    private function positiveId(mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value)
            || (int) $value < 1 || (string) (int) $value !== (string) $value) {
            throw new RuntimeException('Invalid GitHub numeric identifier.');
        }
        return (int) $value;
    }

    private function externalUserId(mixed $value): ?string
    {
        return $value === null ? null : (string) $this->positiveId($value);
    }

    private function text(mixed $value, int $max, bool $nullable): ?string
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (! is_string($value) || (! $nullable && trim($value) === '') || mb_strlen($value) > $max) {
            throw new RuntimeException('Invalid GitHub pull request text.');
        }
        return $value;
    }

    private function sha(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || ! preg_match('/\A[a-f0-9]{40}\z/iD', $value)) {
            throw new RuntimeException('Invalid GitHub pull request SHA.');
        }
        return strtolower($value);
    }

    private function date(mixed $value, bool $nullable = true): ?string
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (! is_string($value) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $value)) {
            throw new RuntimeException('Invalid GitHub pull request date.');
        }
        try {
            return Carbon::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC')->toDateTimeString();
        } catch (\Throwable) {
            throw new RuntimeException('Invalid GitHub pull request date.');
        }
    }
}
