<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryChangeRequest;
use RuntimeException;

/** Strict persistence-boundary contract for normalized PR/MR snapshots. */
final class RepositoryChangeRequestSnapshotValidator
{
    /**
     * Enforce the provider-neutral contract at the persistence boundary, not only
     * inside today's GitHub adapter. Later GitLab/Gitea adapters must satisfy it.
     */
    public function assertValid(mixed $snapshot): void
    {
        if (! is_array($snapshot)
            || ! is_string($snapshot['external_change_request_id'] ?? null)
            || trim($snapshot['external_change_request_id']) === ''
            || mb_strlen($snapshot['external_change_request_id']) > 190
            || ! is_int($snapshot['number'] ?? null) || $snapshot['number'] < 1
            || ! is_string($snapshot['title'] ?? null)
            || trim($snapshot['title']) === '' || mb_strlen($snapshot['title']) > 500
            || ! in_array($snapshot['state'] ?? null, [
                RepositoryChangeRequest::STATE_OPEN,
                RepositoryChangeRequest::STATE_CLOSED,
                RepositoryChangeRequest::STATE_MERGED,
            ], true)
            || ! is_bool($snapshot['is_draft'] ?? null)
            || ! is_bool($snapshot['is_locked'] ?? null)) {
            throw new RuntimeException('Invalid normalized pull request snapshot.');
        }

        $link = $snapshot['web_url'] ?? null;
        $parsed = is_string($link) ? parse_url($link) : false;
        if (! is_string($link) || strlen($link) > 2048
            || preg_match('/[\x00-\x20\x7f\\\\]/', $link)
            || ! is_array($parsed)
            || strtolower($parsed['scheme'] ?? '') !== 'https'
            || empty($parsed['host']) || empty($parsed['path'])
            || isset($parsed['user']) || isset($parsed['pass'])) {
            throw new RuntimeException('Invalid normalized pull request link.');
        }

        foreach (['body' => 65535, 'source_branch' => 255, 'target_branch' => 255,
            'source_repository' => 255, 'author_external_user_id' => 190,
            'author_login' => 190] as $field => $max) {
            if (array_key_exists($field, $snapshot)
                && $snapshot[$field] !== null
                && (! is_string($snapshot[$field]) || mb_strlen($snapshot[$field]) > $max)) {
                throw new RuntimeException('Invalid normalized pull request '.$field.'.');
            }
        }

        foreach (['head_sha', 'base_sha', 'merge_commit_sha'] as $field) {
            if (array_key_exists($field, $snapshot) && $snapshot[$field] !== null
                && (! is_string($snapshot[$field])
                    || ! preg_match('/\A[a-f0-9]{40}\z/D', $snapshot[$field]))) {
                throw new RuntimeException('Invalid normalized pull request SHA.');
            }
        }

        foreach (['remote_created_at', 'remote_updated_at', 'closed_at', 'merged_at'] as $field) {
            if (! array_key_exists($field, $snapshot)
                || ($snapshot[$field] !== null && (! is_string($snapshot[$field])
                    || ! preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', $snapshot[$field])
                    || ($parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $snapshot[$field], new \DateTimeZone('UTC'))) === false
                    || $parsedDate->format('Y-m-d H:i:s') !== $snapshot[$field]))) {
                throw new RuntimeException('Invalid normalized pull request timestamp.');
            }
        }
        if ($snapshot['remote_updated_at'] === null || $snapshot['remote_created_at'] === null
            || ($snapshot['state'] === RepositoryChangeRequest::STATE_MERGED && $snapshot['merged_at'] === null)
            || ($snapshot['state'] !== RepositoryChangeRequest::STATE_MERGED && $snapshot['merged_at'] !== null)) {
            throw new RuntimeException('Inconsistent normalized pull request dates.');
        }
        if (isset($snapshot['author_external_user_id'])
            && (! ctype_digit($snapshot['author_external_user_id'])
                || $snapshot['author_external_user_id'] === '0')) {
            throw new RuntimeException('Invalid normalized pull request author ID.');
        }
    }
}
