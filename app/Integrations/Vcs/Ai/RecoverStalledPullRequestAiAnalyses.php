<?php

namespace App\Integrations\Vcs\Ai;

use App\Models\PullRequestAiAnalysis;
use Illuminate\Database\Eloquent\Builder;

/**
 * Conservatively close abandoned queue requests, without calling the AI provider.
 *
 * A running request MAY already have incurred provider charges. It is never
 * requeued automatically: a fresh request requires new user consent.
 */
final class RecoverStalledPullRequestAiAnalyses
{
    public const MIN_AGE_MINUTES = 10;

    /**
     * @return array{pending:int,running:int,recovered:int,scanned:int}
     */
    public function run(?int $projectId = null, bool $apply = false, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $cutoff = now()->subMinutes(self::MIN_AGE_MINUTES);

        $query = PullRequestAiAnalysis::query()
            ->when($projectId !== null, fn (Builder $q) => $q->where('project_id', $projectId))
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $pending) => $pending
                    ->where('status', PullRequestAiAnalysis::STATUS_PENDING)
                    ->where('created_at', '<=', $cutoff))
                ->orWhere(fn (Builder $running) => $running
                    ->where('status', PullRequestAiAnalysis::STATUS_RUNNING)
                    ->where(fn (Builder $date) => $date
                        ->where('started_at', '<=', $cutoff)
                        ->orWhere(fn (Builder $missingStart) => $missingStart
                            ->whereNull('started_at')
                            ->where('created_at', '<=', $cutoff)))))
            ->orderBy('id')
            ->limit($limit);

        $counts = ['pending' => 0, 'running' => 0, 'recovered' => 0, 'scanned' => 0];

        foreach ($query->get(['id', 'status']) as $analysis) {
            $counts['scanned']++;
            $counts[$analysis->status]++;
            if (! $apply) {
                continue;
            }

            // Compare-and-set: never overwrite an analysis claimed or completed
            // by a worker after our initial SELECT. The cutoff is rechecked.
            $guard = PullRequestAiAnalysis::query()
                ->whereKey($analysis->id)
                ->where('status', $analysis->status);
            if ($analysis->status === PullRequestAiAnalysis::STATUS_PENDING) {
                $guard->where('created_at', '<=', $cutoff);
                $reason = 'queue_timeout';
            } else {
                $guard->where(fn (Builder $date) => $date
                    ->where('started_at', '<=', $cutoff)
                    ->orWhere(fn (Builder $missingStart) => $missingStart
                        ->whereNull('started_at')
                        ->where('created_at', '<=', $cutoff)));
                $reason = 'outcome_unknown';
            }

            $counts['recovered'] += $guard->update([
                'status' => PullRequestAiAnalysis::STATUS_FAILED,
                'error_code' => $reason,
                'result_markdown' => null,
                'finished_at' => now(),
            ]);
        }

        return $counts;
    }
}
