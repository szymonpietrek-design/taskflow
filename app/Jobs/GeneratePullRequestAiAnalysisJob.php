<?php

namespace App\Jobs;

use App\Integrations\Vcs\Ai\GeneratePullRequestAiAnalysis;
use App\Models\PullRequestAiAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Queue payload: only an analysis ID, never a prompt, diff, token or provider response. */
final class GeneratePullRequestAiAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public readonly int $analysisId) {}

    public function handle(GeneratePullRequestAiAnalysis $generator): void
    {
        $analysis = PullRequestAiAnalysis::query()->find($this->analysisId);
        if ($analysis === null || $analysis->status !== PullRequestAiAnalysis::STATUS_PENDING) {
            return; // Idempotent delivery; never pay for the same analysis twice.
        }

        try {
            $actor = $analysis->requester()->firstOrFail();
            $project = $analysis->project()->firstOrFail();
            $pull = $analysis->changeRequest()->firstOrFail();
            $generator->run(
                actor: $actor,
                project: $project,
                pull: $pull,
                language: (string) $analysis->language,
                includePatches: (bool) $analysis->include_patch_excerpts,
                // Explicit approval was collected when this specific row was queued.
                approveExternalSharing: true,
                approveCodeSharing: (bool) $analysis->include_patch_excerpts,
                pendingAnalysis: $analysis,
            );
        } catch (Throwable) {
            // No exception, prompt or provider response may enter logs/failed_jobs.
            // A generator failure already transitions running -> failed.
            $this->markFailed(false);
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Worker timeout/unexpected exception; never log the supplied exception.
        $this->markFailed(true);
    }

    private function markFailed(bool $workerTerminated): void
    {
        PullRequestAiAnalysis::query()->whereKey($this->analysisId)
            ->whereIn('status', $workerTerminated
                ? [PullRequestAiAnalysis::STATUS_PENDING, PullRequestAiAnalysis::STATUS_RUNNING]
                : [PullRequestAiAnalysis::STATUS_PENDING])
            ->update([
                'status' => PullRequestAiAnalysis::STATUS_FAILED,
                'error_code' => 'generation_failed',
                'result_markdown' => null,
                'finished_at' => now(),
            ]);
    }
}
