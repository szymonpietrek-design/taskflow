<?php

namespace App\Integrations\GitHub;

use RuntimeException;

/** Read-only, bounded GitHub PR reviews. No arbitrary URLs or user OAuth tokens. */
class GitHubPullRequestReviewReader extends GitHubAppClient
{
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 5;

    /**
     * @param list<int> $numbers
     * @return array<int, list<array<string, mixed>>>
     */
    public function reviewsForStoredPullRequests(
        int $installationId,
        string $fullName,
        int $expectedRepositoryId,
        array $numbers,
    ): array {
        if ($installationId < 1 || $expectedRepositoryId < 1
            || ! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)
            || ! array_is_list($numbers) || $numbers === [] || count($numbers) > 10
            || count(array_unique($numbers)) !== count($numbers)) {
            throw new RuntimeException('Invalid GitHub review synchronization batch.');
        }
        foreach ($numbers as $number) {
            if (! is_int($number) || $number < 1) {
                throw new RuntimeException('Invalid GitHub pull request number.');
            }
        }

        $client = $this->api()->withToken($this->installationToken($installationId, ['pull_requests' => 'read']));
        $prefix = '/repos/'.$fullName;
        $repository = $client->get($prefix)->throw()->json();
        if (! is_array($repository) || (int) ($repository['id'] ?? 0) !== $expectedRepositoryId
            || strcasecmp((string) ($repository['full_name'] ?? ''), $fullName) !== 0) {
            throw new RuntimeException('The GitHub repository identity changed. No reviews were saved.');
        }

        $all = [];
        foreach ($numbers as $number) {
            $items = [];
            for ($page = 1; $page <= self::MAX_PAGES + 1; $page++) {
                $response = $client->get($prefix.'/pulls/'.$number.'/reviews', [
                    'per_page' => self::PAGE_SIZE,
                    'page' => $page,
                ])->throw()->json();
                if (! is_array($response) || ! array_is_list($response)) {
                    throw new RuntimeException('Invalid GitHub review page.');
                }
                if ($page > self::MAX_PAGES && $response !== []) {
                    throw new RuntimeException('Pull request review history exceeds 500 items. No reviews were saved.');
                }
                foreach ($response as $review) {
                    if (! is_array($review)) {
                        throw new RuntimeException('Invalid GitHub review item.');
                    }
                    $items[] = $review;
                }
                if (count($response) < self::PAGE_SIZE) {
                    break;
                }
            }
            $all[$number] = $items;
        }

        return $all;
    }
}
