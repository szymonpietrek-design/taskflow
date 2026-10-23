<?php

namespace App\Integrations\Vcs\Ai;

use App\Jobs\GeneratePullRequestAiAnalysisJob;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

/** Accept one explicitly approved request without running the external model in HTTP. */
final class QueuePullRequestAiAnalysis
{
    public function __construct(
        private readonly PullRequestAiAnalysisHistory $history,
        private readonly GeneratePullRequestAiAnalysis $analyze,
        private readonly PullRequestAiUsage $usage,
    ) {}

    public function enqueue(User $actor, Project $project, RepositoryChangeRequest $pull, string $language, bool $includePatches): PullRequestAiAnalysis
    {
        Gate::forUser($actor)->authorize('manageManagers', $project);
        if ($actor->disabled_at !== null || $project->archived_at !== null
            || (int) $pull->repository()->value('project_id') !== (int) $project->id) {
            throw new AuthorizationException('AI analysis cannot be requested for this project.');
        }
        $this->analyze->validateForQueue($project, $pull, $includePatches);

        $analysis = DB::transaction(function () use ($actor, $project, $pull, $language, $includePatches): PullRequestAiAnalysis {
            // Serialize competing requests for this project in the same way as
            // the version allocator; do not queue another paid request while active.
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $quota = $this->usage->quota($project);
            if (! $quota['allowed']) {
                throw new PullRequestAiQuotaExceeded('Project AI analysis quota exceeded.');
            }
            $latest = $this->history->forPull($actor, $project, $pull, 1)->first();
            if ($latest !== null && in_array($latest->status, [PullRequestAiAnalysis::STATUS_PENDING, PullRequestAiAnalysis::STATUS_RUNNING], true)) {
                throw new RuntimeException('Analysis already queued or running.');
            }
            return $this->history->createPending($actor, $project, $pull, $language, $includePatches);
        }, 3);

        try {
            // Job payload is just the analysis primary key. Never serialize
            // code snippets, credentials, consent form bodies, or prompts.
            GeneratePullRequestAiAnalysisJob::dispatch((int) $analysis->id)
                ->onConnection('ai_analyses')
                ->onQueue('ai-analyses')
                ->afterCommit();
        } catch (Throwable) {
            PullRequestAiAnalysis::query()->whereKey($analysis->id)
                ->where('status', PullRequestAiAnalysis::STATUS_PENDING)
                ->update(['status' => PullRequestAiAnalysis::STATUS_FAILED,
                    'error_code' => 'queue_unavailable', 'finished_at' => now()]);
            throw new RuntimeException('AI queue is unavailable.');
        }

        return $analysis;
    }
}
