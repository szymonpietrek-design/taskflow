<?php

namespace App\Integrations\GitHub;

use RuntimeException;

/** Read only changed-file metadata, never source, blob contents or patch hunks. */
class GitHubCommitFileReader extends GitHubAppClient
{
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 3;

    /**
     * @param list<string> $shas
     * @return array<string,list<array<string,mixed>>>
     */
    public function filesForStoredCommits(int $installationId, string $fullName, int $expectedRepositoryId, array $shas): array
    {
        if ($installationId < 1 || $expectedRepositoryId < 1
            || ! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)
            || ! array_is_list($shas) || count($shas) > 5) {
            throw new RuntimeException('Invalid commit file batch.');
        }
        $seen = [];
        foreach ($shas as $sha) {
            if (! is_string($sha) || ! preg_match('/\A[a-f0-9]{40}\z/iD', $sha)
                || isset($seen[strtolower($sha)])) {
                throw new RuntimeException('Invalid stored commit SHA.');
            }
            $seen[strtolower($sha)] = true;
        }
        if ($shas === []) {
            return [];
        }

        $client = $this->api()->withToken($this->installationToken($installationId));
        $prefix = '/repos/'.$fullName;
        $repo = $client->get($prefix)->throw()->json();
        if (! is_array($repo) || (int) ($repo['id'] ?? 0) !== $expectedRepositoryId
            || strcasecmp((string) ($repo['full_name'] ?? ''), $fullName) !== 0) {
            throw new RuntimeException('GitHub repository identity mismatch.');
        }

        $results = [];
        foreach ($shas as $requestedSha) {
            $sha = strtolower($requestedSha);
            $files = [];
            $paths = [];
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                // Pin every page to the immutable SHA, not a moving branch HEAD.
                $commit = $client->get($prefix.'/commits/'.$sha, [
                    'per_page' => self::PAGE_SIZE,
                    'page' => $page,
                ])->throw()->json();
                if (! is_array($commit) || strcasecmp((string) ($commit['sha'] ?? ''), $sha) !== 0
                    || ! is_array($commit['files'] ?? null) || ! array_is_list($commit['files'])) {
                    throw new RuntimeException('Invalid commit file response.');
                }
                $batch = $commit['files'];
                if (count($batch) > self::PAGE_SIZE) {
                    throw new RuntimeException('Invalid commit file page size.');
                }
                foreach ($batch as $entry) {
                    $file = $this->normalize($entry);
                    if (isset($paths[$file['path_hash']])) {
                        throw new RuntimeException('Duplicate path across commit file pages.');
                    }
                    $paths[$file['path_hash']] = true;
                    $files[] = $file;
                }
                if (count($batch) < self::PAGE_SIZE) {
                    break;
                }
                if ($page === self::MAX_PAGES) {
                    // GitHub may truncate lists above 300 files. Never save an ambiguous partial snapshot.
                    throw new RuntimeException('Commit contains too many files for a complete snapshot.');
                }
            }
            $results[$sha] = $files;
        }

        return $results;
    }

    /** @return array<string,mixed> */
    private function normalize(mixed $entry): array
    {
        if (! is_array($entry)) {
            throw new RuntimeException('Invalid changed-file entry.');
        }
        $path = $this->path($entry['filename'] ?? null);
        $previousPath = isset($entry['previous_filename']) ? $this->path($entry['previous_filename']) : null;
        $status = $entry['status'] ?? null;
        if (! in_array($status, ['added', 'modified', 'removed', 'renamed', 'copied', 'changed', 'unchanged'], true)
            || ($status === 'renamed' && $previousPath === null)) {
            throw new RuntimeException('Invalid changed-file status.');
        }
        foreach (['additions', 'deletions', 'changes'] as $key) {
            if (! is_int($entry[$key] ?? null) || $entry[$key] < 0 || $entry[$key] > 1000000000) {
                throw new RuntimeException('Invalid changed-line count.');
            }
        }

        return [
            'path' => $path,
            'path_hash' => hash('sha256', $path),
            'previous_path' => $previousPath,
            'status' => $status,
            'additions' => $entry['additions'],
            'deletions' => $entry['deletions'],
            'changes' => $entry['changes'],
        ];
    }

    private function path(mixed $path): string
    {
        if (! is_string($path) || $path === '' || mb_strlen($path) > 1024
            || ! mb_check_encoding($path, 'UTF-8') || str_contains($path, "\0")
            || preg_match('/[\r\n]/', $path) || str_starts_with($path, '/')
            || preg_match('~(^|/)\.\.(/|$)~', $path)) {
            throw new RuntimeException('Invalid commit file path.');
        }
        return $path;
    }
}
