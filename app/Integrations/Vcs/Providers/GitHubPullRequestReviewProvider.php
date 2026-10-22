<?php

namespace App\Integrations\Vcs\Providers;

use App\Integrations\GitHub\GitHubPullRequestReviewReader;
use App\Integrations\Vcs\Contracts\RepositoryChangeRequestReviewProvider;
use App\Models\RepositoryConnection;
use Illuminate\Support\Carbon;
use RuntimeException;

final class GitHubPullRequestReviewProvider implements RepositoryChangeRequestReviewProvider
{
    public function __construct(private readonly GitHubPullRequestReviewReader $reader) {}

    public function reviewsForStoredRequests(RepositoryConnection $repository, array $numbers): array
    {
        $repository->loadMissing('connection.instance');
        $instance = $repository->connection?->instance;
        if ($instance?->provider !== 'github'
            || $instance->base_url !== GitHubInstance::BASE_URL
            || $instance->instance_key !== GitHubInstance::key()) {
            throw new RuntimeException('Unsupported GitHub instance for pull request reviews.');
        }

        $installationId = $this->positiveId($repository->connection->external_connection_id);
        $repositoryId = $this->positiveId($repository->external_repository_id);
        $raw = $this->reader->reviewsForStoredPullRequests($installationId, $repository->full_name, $repositoryId, $numbers);
        if (count($raw) !== count($numbers)) {
            throw new RuntimeException('Incomplete GitHub review response.');
        }

        $normalized = [];
        foreach ($numbers as $number) {
            if (! isset($raw[$number]) || ! is_array($raw[$number]) || ! array_is_list($raw[$number])) {
                throw new RuntimeException('Invalid GitHub reviews response.');
            }
            $normalized[$number] = array_map(fn (array $item): array => $this->normalize($item, $repository->full_name, $number), $raw[$number]);
        }
        return $normalized;
    }

    private function normalize(array $item, string $fullName, int $number): array
    {
        $reviewId = $this->positiveId($item['id'] ?? null);
        $state = strtolower((string) ($item['state'] ?? ''));
        if (! in_array($state, ['approved', 'changes_requested', 'commented', 'dismissed', 'pending'], true)) {
            throw new RuntimeException('Unknown GitHub review state.');
        }
        $reviewer = $item['user'] ?? null;
        if ($reviewer !== null && (! is_array($reviewer)
            || ! is_string($reviewer['login'] ?? null)
            || trim($reviewer['login']) === '' || mb_strlen($reviewer['login']) > 190)) {
            throw new RuntimeException('Invalid GitHub review author.');
        }
        $reviewerId = $reviewer === null ? null : (string) $this->positiveId($reviewer['id'] ?? null);
        $body = $item['body'] ?? null;
        if ($body !== null && (! is_string($body) || mb_strlen($body) > 65535)) {
            throw new RuntimeException('Invalid GitHub review text.');
        }
        $sha = $item['commit_id'] ?? null;
        if ($sha !== null && (! is_string($sha) || ! preg_match('/\A[a-f0-9]{40}\z/iD', $sha))) {
            throw new RuntimeException('Invalid GitHub review commit SHA.');
        }
        $submitted = $item['submitted_at'] ?? null;
        if ($submitted !== null && (! is_string($submitted)
            || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $submitted))) {
            throw new RuntimeException('Invalid GitHub review date.');
        }
        if ($state !== 'pending' && $submitted === null) {
            throw new RuntimeException('A submitted review must have a submission timestamp.');
        }
        if ($submitted !== null) {
            try {
                $submitted = Carbon::createFromFormat('!Y-m-d\TH:i:s\Z', $submitted, 'UTC')->toDateTimeString();
            } catch (\Throwable) {
                throw new RuntimeException('Invalid GitHub review date.');
            }
        }

        return [
            'external_review_id' => (string) $reviewId,
            'reviewer_external_user_id' => $reviewerId,
            'reviewer_login' => $reviewer['login'] ?? null,
            'state' => $state,
            'body' => $body,
            // Construct trusted GitHub link ourselves, never trust a provider-supplied URL.
            'web_url' => 'https://github.com/'.$fullName.'/pull/'.$number.'#pullrequestreview-'.$reviewId,
            'commit_sha' => $sha === null ? null : strtolower($sha),
            'submitted_at' => $submitted,
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
}
