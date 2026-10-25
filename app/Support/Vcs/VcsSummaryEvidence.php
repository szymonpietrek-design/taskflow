<?php

namespace App\Support\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryCommit;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Read-only, provider-neutral summaries. Evidence is not proof that a task shipped. */
final class VcsSummaryEvidence
{
    /**
     * Summarise evidence already stored by the VCS integration. No remote API calls
     * and no raw patches/source code are disclosed to the report AI provider.
     *
     * @return array{commits:list<array<string,mixed>>,pull_requests:list<array<string,mixed>>}
     */
    public function forPeriod(int $projectId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        $commits = RepositoryCommit::query()
            ->whereHas('repository', fn ($q) => $q->where('project_id', $projectId))
            ->whereBetween('committed_at', [$start, $end])
            ->orderBy('committed_at')->orderBy('id')->limit(31)->get();
        $prs = RepositoryChangeRequest::query()
            ->whereHas('repository', fn ($q) => $q->where('project_id', $projectId))
            ->where(fn ($q) => $q->whereBetween('remote_created_at', [$start, $end])
                ->orWhereBetween('merged_at', [$start, $end]))
            ->orderBy('id')->limit(21)->get();

        if ($commits->count() > 30 || $prs->count() > 20) {
            throw new InvalidArgumentException(__('Too much VCS activity for one AI report. Choose a shorter period.'));
        }

        // Limit file metadata across ALL PRs, not just per PR. Large repositories
        // must not silently dominate the selected tasks and worklogs context.
        $remainingFiles = 60;
        $pullRequests = [];
        foreach ($prs as $item) {
            $filesAreCurrent = $item->files_synced_at !== null
                && is_string($item->head_sha) && $item->head_sha !== ''
                && hash_equals((string) $item->head_sha, (string) $item->files_head_sha);
            // The latest PR file snapshot cannot prove what its code contained at
            // the end of an older reporting period if the PR changed afterwards.
            $snapshotInPeriod = $item->remote_updated_at !== null
                && $item->remote_updated_at->lessThanOrEqualTo($end);
            $filesAvailable = $filesAreCurrent && $snapshotInPeriod;
            $files = [];
            $fileCount = null;
            if ($filesAvailable) {
                $fileCount = $item->files()->count();
                $take = min(12, $remainingFiles);
                if ($take > 0) {
                    $files = $item->files()->orderBy('id')->limit($take)->get()
                        ->map(fn ($file): array => [
                            'path' => $this->shorten($file->path, 180),
                            'status' => $file->status,
                            'additions' => $file->additions,
                            'deletions' => $file->deletions,
                        ])->all();
                    $remainingFiles -= count($files);
                }
            }

            $pullRequests[] = [
                'number' => $item->number,
                'title' => $this->shorten($item->title, 200),
                'state' => $item->state,
                'created_at' => $item->remote_created_at?->toDateString(),
                'merged_at' => $item->merged_at?->toDateString(),
                // A PR created in the period can be merged later; that later
                // integration must not be reported as accomplished in this period.
                'merged_in_period' => $item->merged_at?->betweenIncluded($start, $end) ?? false,
                // PR target is stored by the provider. Never guess an individual
                // commit's branch from repository default-branch metadata.
                'target_branch' => $item->target_branch === null
                    ? null : $this->shorten($item->target_branch, 120),
                'files_snapshot' => $filesAvailable ? 'current' : 'unavailable_or_stale',
                'file_count' => $fileCount,
                'files_included' => count($files),
                'files' => $files,
            ];
        }

        // Keep commit metadata bounded independently from PR file metadata.
        // The report only reads committed-at-in-period SHAs; a stored snapshot is
        // bound to an immutable SHA and is safe for historical reporting.
        $remainingCommitFiles = 60;
        // Divide the budget fairly across all commits, including late-period work.
        $perCommitFileLimit = $commits->isEmpty() ? 0 : min(8, intdiv(60, $commits->count()));
        $summarizedCommits = [];
        foreach ($commits as $item) {
            $files = [];
            $fileCount = $item->files_synced_at === null ? null : $item->files_count;
            if ($fileCount !== null && $remainingCommitFiles > 0) {
                $files = $item->files()->orderBy('id')->limit(min($perCommitFileLimit, $remainingCommitFiles))
                    ->get()->map(fn ($file): array => [
                        'path' => $this->shorten($file->path, 180),
                        'status' => $file->status,
                        'additions' => $file->additions,
                        'deletions' => $file->deletions,
                    ])->all();
                $remainingCommitFiles -= count($files);
            }
            $lines = preg_split('/\r?\n/', (string) $item->message, 2);
            $details = trim($lines[1] ?? '');
            $summary = [
                'sha' => substr($item->sha, 0, 12),
                'subject' => $this->shorten($lines[0] ?? '', 200),
                'details' => $details === '' ? null : $this->shorten($details, 600),
                'date' => $item->committed_at?->toDateString(),
                'files_snapshot' => $fileCount === null ? 'unavailable' : 'complete',
                'file_count' => $fileCount,
                'files_included' => count($files),
                'files' => $files,
            ];
            $summarizedCommits[] = $summary;
        }

        return [
            // These are authoritative, scoped counts for the selected project and period.
            // The AI must not estimate commit totals from changed-file rows.
            'commit_count' => count($summarizedCommits),
            'pull_request_count' => count($pullRequests),
            'commits' => $summarizedCommits,
            'pull_requests' => $pullRequests,
        ];
    }

    /** @param list<string> $completedTaskReferences
     *  @return array{commits:list<array<string,mixed>>,pull_requests:list<array<string,mixed>>}
     */
    public function forRelease(int $projectId, array $completedTaskReferences): array
    {
        if ($completedTaskReferences === []) {
            return ['commits' => [], 'pull_requests' => []];
        }
        // Limit is applied before filtering so we never submit misleading, arbitrarily truncated history.
        $commits = RepositoryCommit::query()
            ->whereHas('repository', fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('id')->limit(501)->get();
        $prs = RepositoryChangeRequest::query()
            ->whereHas('repository', fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('id')->limit(201)->get();

        if ($commits->count() > 500 || $prs->count() > 200) {
            throw new InvalidArgumentException(__('Too much VCS history for one release. Generate without VCS context.'));
        }

        $matchingCommits = $commits->filter(fn (RepositoryCommit $item): bool =>
            $this->referencesCompletedTask(strtok($item->message, "\n") ?: '', $completedTaskReferences));
        $matchingPrs = $prs->filter(fn (RepositoryChangeRequest $item): bool =>
            $this->referencesCompletedTask((string) $item->title, $completedTaskReferences));
        if ($matchingCommits->count() > 20 || $matchingPrs->count() > 15) {
            throw new InvalidArgumentException(__('Too many linked VCS changes for one release. Generate without VCS context.'));
        }

        return [
            'commits' => $matchingCommits->map(fn (RepositoryCommit $item): array => [
                'subject' => $this->shorten(strtok($item->message, "\n") ?: '', 200),
                'sha' => substr($item->sha, 0, 12),
            ])->values()->all(),
            'pull_requests' => $matchingPrs->map(fn (RepositoryChangeRequest $item): array => [
                'number' => $item->number,
                'title' => $this->shorten($item->title, 200),
                'state' => $item->state,
                'merged_at' => $item->merged_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    /** @return array{commits:int,pull_requests:int} */
    public function countsForPeriod(int $projectId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        return [
            'commits' => RepositoryCommit::query()->whereHas('repository', fn ($q) => $q->where('project_id', $projectId))
                ->whereBetween('committed_at', [$start, $end])->count(),
            'pull_requests' => RepositoryChangeRequest::query()->whereHas('repository', fn ($q) => $q->where('project_id', $projectId))
                ->where(fn ($q) => $q->whereBetween('remote_created_at', [$start, $end])
                    ->orWhereBetween('merged_at', [$start, $end]))->count(),
        ];
    }

    private function referencesCompletedTask(string $text, array $references): bool
    {
        foreach ($references as $reference) {
            if (preg_match('/(?<![A-Z0-9])'.preg_quote($reference, '/').'(?![A-Z0-9])/i', $text)) {
                return true;
            }
        }

        return false;
    }

    private function shorten(string $text, int $max): string
    {
        $text = trim($text);
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max).'…' : $text;
    }
}
