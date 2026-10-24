<?php

namespace App\Support\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryCommit;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Read-only, provider-neutral summaries. Evidence is not proof that a task shipped. */
final class VcsSummaryEvidence
{
    /** @return array{commits:list<array<string,mixed>>,pull_requests:list<array<string,mixed>>} */
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

        return [
            'commits' => $commits->map(fn (RepositoryCommit $item): array => [
                'sha' => substr($item->sha, 0, 12),
                'subject' => $this->shorten(strtok($item->message, "\n") ?: '', 200),
                'date' => $item->committed_at?->toDateString(),
            ])->all(),
            'pull_requests' => $prs->map(fn (RepositoryChangeRequest $item): array => [
                'number' => $item->number,
                'title' => $this->shorten($item->title, 200),
                'state' => $item->state,
                'created_at' => $item->remote_created_at?->toDateString(),
                'merged_at' => $item->merged_at?->toDateString(),
            ])->all(),
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
