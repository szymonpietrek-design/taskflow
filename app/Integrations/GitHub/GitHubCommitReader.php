<?php

namespace App\Integrations\GitHub;

use RuntimeException;

/**
 * Reads only commits reachable after a previously stored SHA. No AI or full-history import.
 */
class GitHubCommitReader extends GitHubAppClient
{
    private const MAX_COMMITS = 500;

    /**
     * @return array{head_sha: string, commits: list<array{sha:string,message:string,author_name:?string,author_login:?string,committed_at:?string,author_github_user_id:?int}>}
     */
    public function changesSince(int $installationId, string $fullName, string $branch, string $baseSha, int $expectedRepositoryId): array
    {
        if (! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)
            || ! preg_match('/\A[a-f0-9]{40}\z/D', $baseSha)
            || $branch === '' || $expectedRepositoryId < 1) {
            throw new RuntimeException('Invalid tracked GitHub repository or SHA.');
        }

        $client = $this->api()->withToken($this->installationToken($installationId));
        $prefix = '/repos/'.$fullName;
        $metadata = $client->get($prefix)->throw()->json();
        if ((int) ($metadata['id'] ?? 0) !== $expectedRepositoryId
            || ($metadata['default_branch'] ?? null) !== $branch
            || strcasecmp((string) ($metadata['full_name'] ?? ''), $fullName) !== 0) {
            throw new RuntimeException('Repository identity or default branch changed. Reconnect explicitly before synchronizing.');
        }

        $head = $client->get($prefix.'/commits/'.rawurlencode($branch))->throw()->json('sha');
        if (! is_string($head) || ! preg_match('/\A[a-fA-F0-9]{40}\z/D', $head)) {
            throw new RuntimeException('GitHub returned an invalid HEAD SHA.');
        }
        $head = strtolower($head);
        if ($head === $baseSha) {
            return ['head_sha' => $head, 'commits' => []];
        }

        $commits = [];
        for ($page = 1; $page <= 6; $page++) {
            // Compare against the pinned HEAD, never a moving branch reference.
            $compare = $client->get($prefix.'/compare/'.$baseSha.'...'.$head, [
                'per_page' => 100,
                'page' => $page,
            ])->throw()->json();

            if (! is_array($compare) || ($compare['status'] ?? null) !== 'ahead'
                || (int) ($compare['behind_by'] ?? -1) !== 0) {
                throw new RuntimeException('GitHub branch history was rewritten or diverged. The saved cursor was not changed.');
            }
            if ((int) ($compare['total_commits'] ?? 0) > self::MAX_COMMITS
                || (int) ($compare['ahead_by'] ?? 0) > self::MAX_COMMITS) {
                throw new RuntimeException('More than 500 new commits were detected. Synchronization was stopped without changing the cursor.');
            }
            $items = $compare['commits'] ?? null;
            if (! is_array($items)) {
                throw new RuntimeException('GitHub returned malformed commit comparison data.');
            }
            foreach ($items as $item) {
                $sha = $item['sha'] ?? null;
                $message = $item['commit']['message'] ?? null;
                if (! is_string($sha) || ! preg_match('/\A[a-fA-F0-9]{40}\z/D', $sha)
                    || ! is_string($message)) {
                    throw new RuntimeException('GitHub returned malformed commit metadata.');
                }
                $authorName = $item['commit']['author']['name'] ?? null;
                $authorLogin = $item['author']['login'] ?? null;
                $committedAt = $item['commit']['committer']['date'] ?? null;
                $commits[strtolower($sha)] = [
                    'sha' => strtolower($sha),
                    'message' => mb_substr($message, 0, 16000),
                    'author_name' => is_string($authorName) ? mb_substr($authorName, 0, 190) : null,
                    'author_login' => is_string($authorLogin) ? mb_substr($authorLogin, 0, 190) : null,
                    // Never infer identity from author name, email, or login.
                    'author_github_user_id' => self::githubUserId($item['author']['id'] ?? null),
                    'committed_at' => is_string($committedAt) ? $committedAt : null,
                ];
            }
            if (count($commits) > self::MAX_COMMITS) {
                throw new RuntimeException('Too many new commits to safely synchronize in one request.');
            }
            if (count($items) < 100) {
                break;
            }
            if ($page === 6) {
                throw new RuntimeException('GitHub comparison exceeded the synchronization safety limit.');
            }
        }

        if ($commits === [] || ! array_key_exists($head, $commits)) {
            throw new RuntimeException('Incomplete GitHub comparison: the HEAD commit is missing. Cursor was not changed.');
        }

        return ['head_sha' => $head, 'commits' => array_values($commits)];
    }

    /**
     * Recheck identities only for SHAs already stored by TaskFlow.
     * We never list or import repository history during author backfill.
     *
     * @param list<string> $shas
     * @return array<string, ?int> SHA => verified GitHub author ID (or null)
     */
    public function authorIdentitiesForStoredCommits(int $installationId, string $fullName, int $expectedRepositoryId, array $shas): array
    {
        if (! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)
            || $expectedRepositoryId < 1 || count($shas) > 10) {
            throw new RuntimeException('Invalid tracked repository or author lookup batch.');
        }
        foreach ($shas as $sha) {
            if (! is_string($sha) || ! preg_match('/\A[a-fA-F0-9]{40}\z/D', $sha)) {
                throw new RuntimeException('Invalid stored commit SHA.');
            }
        }
        if ($shas === []) {
            return [];
        }

        $client = $this->api()->withToken($this->installationToken($installationId));
        $prefix = '/repos/'.$fullName;
        $metadata = $client->get($prefix)->throw()->json();
        if ((int) ($metadata['id'] ?? 0) !== $expectedRepositoryId
            || strcasecmp((string) ($metadata['full_name'] ?? ''), $fullName) !== 0) {
            throw new RuntimeException('Repository identity changed. Author lookup was stopped.');
        }

        $identities = [];
        foreach ($shas as $sha) {
            $sha = strtolower($sha);
            $item = $client->get($prefix.'/commits/'.$sha)->throw()->json();
            if (! is_array($item) || strcasecmp((string) ($item['sha'] ?? ''), $sha) !== 0) {
                throw new RuntimeException('GitHub returned mismatched commit metadata.');
            }
            $identities[$sha] = self::githubUserId($item['author']['id'] ?? null);
        }

        return $identities;
    }

    private static function githubUserId(mixed $id): ?int
    {
        return is_int($id) && $id > 0 ? $id : null;
    }

}
