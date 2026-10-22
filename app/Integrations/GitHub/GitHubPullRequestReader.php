<?php

namespace App\Integrations\GitHub;

use Illuminate\Support\Carbon;
use RuntimeException;

/** Read PR snapshots through a narrowly scoped GitHub App installation token. */
class GitHubPullRequestReader extends GitHubAppClient
{
    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 5;

    /**
     * @return list<array<string, mixed>> Raw GitHub PR objects; never persisted without normalization.
     */
    public function listPullRequests(
        int $installationId,
        string $fullName,
        int $expectedRepositoryId,
        ?Carbon $updatedSince = null,
    ): array {
        if ($installationId < 1 || $expectedRepositoryId < 1
            || ! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)) {
            throw new RuntimeException('Invalid GitHub repository identifier.');
        }

        // Existing commit synchronization still requests only contents:read.
        $client = $this->api()->withToken($this->installationToken($installationId, ['pull_requests' => 'read']));
        $prefix = '/repos/'.$fullName;
        $metadata = $client->get($prefix)->throw()->json();
        if (! is_array($metadata)
            || (int) ($metadata['id'] ?? 0) !== $expectedRepositoryId
            || strcasecmp((string) ($metadata['full_name'] ?? ''), $fullName) !== 0) {
            throw new RuntimeException('The GitHub repository identity changed. No pull requests were saved.');
        }

        $items = [];
        for ($page = 1; $page <= self::MAX_PAGES + 1; $page++) {
            $batch = $client->get($prefix.'/pulls', [
                'state' => 'all',
                'sort' => 'updated',
                'direction' => 'desc',
                'per_page' => self::PAGE_SIZE,
                'page' => $page,
            ])->throw()->json();
            if (! is_array($batch) || ! array_is_list($batch)) {
                throw new RuntimeException('GitHub returned an invalid pull request page.');
            }
            $reachedOldData = false;
            foreach ($batch as $item) {
                if (! is_array($item) || ! is_string($item['updated_at'] ?? null)) {
                    throw new RuntimeException('GitHub returned an invalid pull request timestamp.');
                }
                try {
                    $updated = Carbon::parse($item['updated_at'])->utc();
                } catch (\Throwable) {
                    throw new RuntimeException('GitHub returned an invalid pull request timestamp.');
                }
                // A five-minute overlap catches equal timestamps and repeated page boundaries.
                if ($updatedSince !== null && $updated->lt($updatedSince)) {
                    $reachedOldData = true;
                    break;
                }
                if ($page > self::MAX_PAGES) {
                    throw new RuntimeException('The pull request history exceeds the 500-item synchronization limit. Nothing was saved.');
                }
                $items[] = $item;
            }
            if ($reachedOldData || count($batch) < self::PAGE_SIZE) {
                break;
            }
        }

        return $items;
    }
}
