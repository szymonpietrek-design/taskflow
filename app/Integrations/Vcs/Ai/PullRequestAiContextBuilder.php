<?php

namespace App\Integrations\Vcs\Ai;

use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Builds an in-memory, provider-neutral evidence snapshot. No network, AI calls,
 * database writes or logging. Caller must separately authorize use/disclosure.
 * External text, including any patch, is untrusted DATA, never an instruction.
 */
final class PullRequestAiContextBuilder
{
    private const MAX_FILES = 50;
    private const MAX_REVIEWS = 30;
    private const MAX_REPOSITORY_COMMITS = 10;
    private const MAX_PATCHES = 8;
    private const PATCH_BUDGET_BYTES = 12288;

    public function __construct(private readonly PullRequestAiPatchPolicy $patchPolicy) {}

    /**
     * @return array<string, mixed>
     * @throws ModelNotFoundException
     */
    public function build(Project $project, int $pullNumber, bool $includePatches = false): array
    {
        if ($pullNumber < 1) {
            throw (new ModelNotFoundException())->setModel(RepositoryChangeRequest::class);
        }

        $repository = RepositoryConnection::query()->where('project_id', $project->getKey())
            ->firstOrFail();
        $pull = $repository->changeRequests()->where('number', $pullNumber)->firstOrFail();

        return $this->buildForPull($project, $pull, $includePatches);
    }

    /**
     * Build evidence for the exact change request being analyzed. Current schema
     * allows one repository per project, but this explicit binding prevents
     * accidentally using another source if that restriction changes later.
     *
     * @return array<string, mixed>
     */
    public function buildForPull(Project $project, RepositoryChangeRequest $pull, bool $includePatches = false): array
    {
        $repository = $pull->repository()->firstOrFail();
        if ((int) $repository->project_id !== (int) $project->getKey()) {
            throw (new ModelNotFoundException())->setModel(RepositoryChangeRequest::class);
        }

        // Database snapshots are valid only for the exact HEAD they were collected from.
        $current = $pull->files_synced_at !== null
            && is_string($pull->head_sha) && $pull->head_sha !== ''
            && hash_equals((string) $pull->head_sha, (string) $pull->files_head_sha);

        $data = [
            'schema' => 'taskflow.vcs.ai-context.v1',
            'source_kind' => 'untrusted_repository_data',
            'transmitted_to_ai' => false,
            'project_id' => (int) $project->getKey(),
            'repository_id' => (int) $repository->getKey(),
            'pull_request' => [
                'number' => (int) $pull->number,
                'title' => $this->text($pull->title, 160),
                'state' => $pull->state,
                'is_draft' => (bool) $pull->is_draft,
                'author_provider_login' => $this->text($pull->author_login, 80),
                'head_sha' => $pull->head_sha,
                'remote_updated_at' => $pull->remote_updated_at?->toIso8601String(),
            ],
            'files_snapshot' => [
                'status' => $current ? 'current' : 'missing_or_stale',
                'head_sha' => $current ? $pull->files_head_sha : null,
                'synced_at' => $current ? $pull->files_synced_at?->toIso8601String() : null,
            ],
            'limits' => [
                'files' => self::MAX_FILES,
                'reviews' => self::MAX_REVIEWS,
                'repository_commits' => self::MAX_REPOSITORY_COMMITS,
                'patches' => self::MAX_PATCHES,
                'patch_bytes_total' => self::PATCH_BUDGET_BYTES,
            ],
        ];

        // Reviews: statuses and dates only. Raw comments may include confidential data.
        // PENDING reviews never count as completed work.
        $reviews = $pull->reviews()->where('state', '!=', RepositoryChangeRequestReview::STATE_PENDING)
            ->orderBy('id')->limit(self::MAX_REVIEWS + 1)->get();
        $data['reviews'] = $reviews->take(self::MAX_REVIEWS)->map(fn (RepositoryChangeRequestReview $review): array => [
            'provider_review_id' => (string) $review->external_review_id,
            'provider_reviewer_login' => $this->text($review->reviewer_login, 80),
            'state' => $review->state,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
        ])->values()->all();
        $data['reviews_omitted'] = $reviews->count() > self::MAX_REVIEWS;

        // Repository commits are NOT claimed to belong to this PR.
        $commits = $repository->commits()->orderByDesc('committed_at')->orderByDesc('id')
            ->limit(self::MAX_REPOSITORY_COMMITS + 1)->get();
        $data['recent_repository_commits_unrelated_to_pr'] = $commits
            ->take(self::MAX_REPOSITORY_COMMITS)->map(fn ($commit): array => [
                'sha' => $commit->sha,
                // strtok('') returns false; a blank commit message is still valid metadata.
                'message_summary' => $this->text(explode("\n", (string) $commit->message, 2)[0], 160),
                'provider_author_login' => $this->text($commit->author_login, 80),
                'committed_at' => $commit->committed_at?->toIso8601String(),
            ])->values()->all();
        $data['repository_commits_omitted'] = $commits->count() > self::MAX_REPOSITORY_COMMITS;

        $data['files'] = [];
        $data['files_count'] = $current ? $pull->files()->count() : 0;
        $data['files_omitted'] = false;
        $data['patches_included'] = 0;
        $data['patches_skipped_by_reason'] = [];
        if (! $current) {
            return $data;
        }

        $files = $pull->files()->orderBy('id')->limit(self::MAX_FILES + 1)->get();
        $data['files_omitted'] = $files->count() > self::MAX_FILES;
        $budget = self::PATCH_BUDGET_BYTES;
        foreach ($files->take(self::MAX_FILES) as $file) {
            $entry = [
                'path' => $this->text($file->path, 512),
                'status' => $file->status,
                'additions' => (int) $file->additions,
                'deletions' => (int) $file->deletions,
            ];
            if ($includePatches) {
                $reason = $this->patchPolicy->inspect((string) $file->path, $file->patch, (bool) $file->patch_truncated);
                if ($reason === null && ($data['patches_included'] >= self::MAX_PATCHES
                    || strlen($file->patch) > $budget)) {
                    $reason = 'budget_exceeded';
                }
                if ($reason === null) {
                    $entry['untrusted_patch'] = $file->patch;
                    $budget -= strlen($file->patch);
                    $data['patches_included']++;
                } else {
                    $data['patches_skipped_by_reason'][$reason] =
                        ($data['patches_skipped_by_reason'][$reason] ?? 0) + 1;
                }
            }
            $data['files'][] = $entry;
        }

        return $data;
    }

    private function text(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            return '[invalid text]';
        }
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '');
        // A coarse warning guard for metadata, not a substitute for security review.
        if (preg_match('/(?:github_pat_|ghp_[a-z0-9_]{12,}|\b(?:password|secret|api[_-]?key|access[_-]?token)\b)/i', $value)) {
            return '[redacted: potentially sensitive metadata]';
        }
        return mb_substr($value, 0, $limit);
    }
}
