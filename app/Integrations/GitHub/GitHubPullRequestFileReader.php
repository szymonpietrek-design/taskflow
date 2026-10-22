<?php

namespace App\Integrations\GitHub;

use RuntimeException;

/** Fetch bounded PR file lists through an installation-scoped GitHub App token. */
class GitHubPullRequestFileReader extends GitHubAppClient
{
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 5; // 500 files per PR per invocation; no silent truncation.
    private const MAX_PATCH_BYTES = 12000;
    private const PATCH_BUDGET_BYTES = 120000;

    /**
     * @param list<array{number:int,head_sha:string}> $pulls
     * @return array<int, list<array<string, mixed>>>
     */
    public function filesForStoredPullRequests(
        int $installationId,
        string $fullName,
        int $expectedRepositoryId,
        array $pulls,
    ): array {
        if ($installationId < 1 || $expectedRepositoryId < 1
            || ! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)
            || ! array_is_list($pulls) || $pulls === [] || count($pulls) > 5) {
            throw new RuntimeException('Invalid pull request file synchronization batch.');
        }
        $seenNumbers = [];
        foreach ($pulls as $pull) {
            if (! is_array($pull) || ! is_int($pull['number'] ?? null) || $pull['number'] < 1
                || ! is_string($pull['head_sha'] ?? null)
                || ! preg_match('/\A[a-f0-9]{40}\z/iD', $pull['head_sha'])
                || isset($seenNumbers[$pull['number']])) {
                throw new RuntimeException('Invalid stored pull request identity.');
            }
            $seenNumbers[$pull['number']] = true;
        }

        $client = $this->api()->withToken($this->installationToken($installationId, ['pull_requests' => 'read']));
        $prefix = '/repos/'.$fullName;
        $repo = $client->get($prefix)->throw()->json();
        if (! is_array($repo) || (int) ($repo['id'] ?? 0) !== $expectedRepositoryId
            || strcasecmp((string) ($repo['full_name'] ?? ''), $fullName) !== 0) {
            throw new RuntimeException('GitHub repository identity mismatch. No file changes were saved.');
        }
        $result = [];
        foreach ($pulls as $pull) {
            $number = $pull['number'];
            // Validate the head before AND after pagination so the file list cannot mix revisions.
            $this->assertSameHead($client, $prefix, $number, $pull['head_sha']);
            $files = [];
            $remainingPatchBudget = self::PATCH_BUDGET_BYTES;
            for ($page = 1; $page <= self::MAX_PAGES + 1; $page++) {
                $raw = $client->get($prefix.'/pulls/'.$number.'/files', [
                    'per_page' => self::PAGE_SIZE, 'page' => $page,
                ])->throw()->json();
                if (! is_array($raw) || ! array_is_list($raw)) {
                    throw new RuntimeException('Invalid GitHub changed files response.');
                }
                if ($page > self::MAX_PAGES && $raw !== []) {
                    throw new RuntimeException('PR changes exceed the 500-file limit. No partial file list was saved.');
                }
                foreach ($raw as $file) {
                    if (! is_array($file)) {
                        throw new RuntimeException('Invalid changed file entry.');
                    }
                    $files[] = $this->normalize($file, $remainingPatchBudget);
                }
                if (count($raw) < self::PAGE_SIZE) {
                    break;
                }
            }
            $this->assertSameHead($client, $prefix, $number, $pull['head_sha']);
            $result[$number] = $files;
        }
        return $result;
    }

    private function assertSameHead($client, string $prefix, int $number, string $sha): void
    {
        $raw = $client->get($prefix.'/pulls/'.$number)->throw()->json();
        if (! is_array($raw) || (int) ($raw['number'] ?? 0) !== $number
            || ! is_string($raw['head']['sha'] ?? null)
            || strcasecmp($raw['head']['sha'], $sha) !== 0) {
            throw new RuntimeException('Pull request HEAD changed. Sync PR metadata before fetching changed files.');
        }
    }

    /** @param array<string, mixed> $file */
    private function normalize(array $file, int &$remainingPatchBudget): array
    {
        $path = $this->path($file['filename'] ?? null);
        $status = $file['status'] ?? null;
        if (! is_string($status) || ! in_array($status, [
            'added', 'modified', 'removed', 'renamed', 'copied', 'changed', 'unchanged',
        ], true)) {
            throw new RuntimeException('Invalid GitHub changed file status.');
        }
        $previous = $file['previous_filename'] ?? null;
        if ($previous !== null) {
            $previous = $this->path($previous);
        }
        if ($status === 'renamed' && $previous === null) {
            throw new RuntimeException('Renamed file lacks its previous path.');
        }
        $additions = $this->count($file['additions'] ?? null);
        $deletions = $this->count($file['deletions'] ?? null);
        $changes = $this->count($file['changes'] ?? null);
        $sha = $file['sha'] ?? null;
        if ($sha !== null && (! is_string($sha) || ! preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/iD', $sha))) {
            throw new RuntimeException('Invalid GitHub blob SHA.');
        }
        $rawPatch = $file['patch'] ?? null;
        if ($rawPatch !== null && ! is_string($rawPatch)) {
            throw new RuntimeException('Invalid GitHub file patch.');
        }
        // Never store partial hunks: omit oversize patches and expose a clear truncation flag.
        $size = $rawPatch === null ? 0 : strlen($rawPatch);
        $omit = $rawPatch === null || $size > self::MAX_PATCH_BYTES || $size > $remainingPatchBudget;
        if (! $omit) {
            $remainingPatchBudget -= $size;
        }
        return [
            'path' => $path,
            'path_hash' => hash('sha256', $path),
            'previous_path' => $previous,
            'status' => $status,
            'additions' => $additions,
            'deletions' => $deletions,
            'changes' => $changes,
            'blob_sha' => $sha === null ? null : strtolower($sha),
            'patch' => $omit ? null : $rawPatch,
            'patch_truncated' => $omit,
        ];
    }

    private function path(mixed $value): string
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > 1024
            || ! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")
            || preg_match('/[\r\n]/', $value) || str_starts_with($value, '/')
            || preg_match('~(^|/)\.\.(/|$)~', $value)) {
            throw new RuntimeException('Invalid GitHub changed file path.');
        }
        return $value;
    }

    private function count(mixed $value): int
    {
        if (! is_int($value) || $value < 0 || $value > 1000000000) {
            throw new RuntimeException('Invalid GitHub changed file statistics.');
        }
        return $value;
    }
}
