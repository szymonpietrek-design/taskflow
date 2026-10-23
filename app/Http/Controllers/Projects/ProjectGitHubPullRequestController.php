<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\Ai\PullRequestAiAnalysisHistory;
use App\Integrations\Vcs\Ai\PullRequestAiUsage;
use App\Models\PullRequestAiAnalysis;
use App\Support\Tasks\TaskMarkdown;
use App\Integrations\Vcs\RepositoryChangeRequestParticipantResolver;
use App\Integrations\Vcs\RepositoryChangeRequestReviewResolver;
use App\Integrations\Vcs\RepositoryChangeRequestFileSynchronizer;
use App\Integrations\Vcs\RepositoryChangeRequestAiSnapshotSynchronizer;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use App\Support\Projects\PullRequestBodyMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only details of a stored GitHub pull request; does not contact GitHub. */
final class ProjectGitHubPullRequestController extends Controller
{
    public function __invoke(Request $request, Project $project, int $number, PullRequestBodyMarkdown $bodyMarkdown, PullRequestAiAnalysisHistory $history, TaskMarkdown $safeMarkdown, PullRequestAiUsage $usage): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);

        $repository = RepositoryConnection::query()
            ->where('project_id', $project->id)
            ->whereHas('connection.instance', fn ($query) => $query
                ->where('provider', 'github')
                ->where('instance_key', GitHubInstance::key())
                ->where('base_url', GitHubInstance::BASE_URL))
            ->with('connection.instance')
            ->firstOrFail();

        $pull = $repository->changeRequests()->where('number', $number)->firstOrFail();
        $submittedReviews = $pull->reviews()->where('state', '!=', RepositoryChangeRequestReview::STATE_PENDING);
        $reviewsCount = (clone $submittedReviews)->count();
        $reviews = $submittedReviews->with('changeRequest')->orderByDesc('submitted_at')->orderByDesc('id')->limit(50)->get();

        $participants = app(RepositoryChangeRequestParticipantResolver::class)
            ->resolve($repository, collect([$pull]))[$pull->id];
        $reviewers = $reviews->isNotEmpty()
            ? app(RepositoryChangeRequestReviewResolver::class)->resolve($repository, $reviews)
            : [];

        // Never expose stored, provider-controlled URLs directly. Build the destination
        // from the connected repository and validated PR number instead.
        $repositoryPath = implode('/', array_map('rawurlencode', explode('/', $repository->full_name)));
        $webUrl = 'https://github.com/'.$repositoryPath.'/pull/'.$pull->number;

        // Only present the snapshot if the PR HEAD still matches its stored revision.
        $filesCurrent = $pull->files_synced_at !== null && $pull->head_sha !== null
            && $pull->head_sha === $pull->files_head_sha;
        $filesCount = $filesCurrent ? $pull->files()->count() : 0;
        $files = $filesCurrent ? $pull->files()->orderBy('path')->limit(100)->get() : collect();

        $analyses = $history->forPull($request->user(), $project, $pull, 12);
        $quota = $usage->quota($project);
        $canAnalyze = $request->user()->can('manageManagers', $project)
            && $request->user()->disabled_at === null
            && $project->archived_at === null;
        $filesReadyForAi = $filesCurrent && $pull->synced_at !== null
            && $pull->synced_at->gte(now()->subMinutes(30))
            && $pull->files_synced_at?->gte(now()->subMinutes(30));

        return Inertia::render('projects/integrations/GitHubPullRequest', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'is_archived' => $project->archived_at !== null,
            ],
            'repository' => ['full_name' => $repository->full_name],
            'pull_request' => [
                'number' => $pull->number,
                'title' => $pull->title,
                // Only sanitized HTML is sent to the browser; never render raw PR HTML with v-html.
                'body_html' => $bodyMarkdown->render($pull->body === null ? null : mb_substr($pull->body, 0, 30000)),
                'web_url' => $webUrl,
                'state' => $pull->state,
                'is_draft' => $pull->is_draft,
                'source_branch' => $pull->source_branch,
                'target_branch' => $pull->target_branch,
                'source_repository' => $pull->source_repository,
                'head_sha' => $pull->head_sha,
                'base_sha' => $pull->base_sha,
                'merge_commit_sha' => $pull->merge_commit_sha,
                'author' => [
                    'login' => $pull->author_login,
                    'taskflow_user' => $participants['author'],
                ],
                'merged_by' => $pull->state === RepositoryChangeRequest::STATE_MERGED ? [
                    'login' => $pull->merged_by_login,
                    'taskflow_user' => $participants['merged_by'],
                ] : null,
                'remote_created_at' => $pull->remote_created_at?->toIso8601String(),
                'remote_updated_at' => $pull->remote_updated_at?->toIso8601String(),
                'closed_at' => $pull->closed_at?->toIso8601String(),
                'merged_at' => $pull->merged_at?->toIso8601String(),
                'synced_at' => $pull->synced_at?->toIso8601String(),
                'reviews_checked_at' => $pull->reviews_checked_at?->toIso8601String(),
            ],
            'reviews_count' => $reviewsCount,
            'reviews' => $reviews->map(fn (RepositoryChangeRequestReview $review): array => [
                'id' => $review->id,
                'state' => $review->state,
                'reviewer_login' => $review->reviewer_login,
                'taskflow_reviewer' => $reviewers[$review->id] ?? null,
                'body' => $review->body === null ? null : mb_substr($review->body, 0, 4000),
                'submitted_at' => $review->submitted_at?->toIso8601String(),
            ])->values(),
            'reviews_limit' => 50,
            'ai' => [
                'can_generate' => $canAnalyze,
                'quota' => $quota,
                'usage_30d' => $usage->last30Days($project),
                'ready' => $filesReadyForAi,
                'history_limit' => 12,
                'history' => $analyses->map(static function (PullRequestAiAnalysis $analysis) use ($safeMarkdown): array {
                    return [
                        'id' => $analysis->id,
                        'version' => $analysis->version,
                        'status' => $analysis->status,
                        'language' => $analysis->language,
                        'source_head_sha' => $analysis->source_head_sha,
                        'include_patch_excerpts' => $analysis->include_patch_excerpts,
                        'ai_provider' => $analysis->ai_provider,
                        'ai_model' => $analysis->ai_model,
                        'prompt_tokens' => $analysis->prompt_tokens,
                        'completion_tokens' => $analysis->completion_tokens,
                        'estimated_cost_usd' => $analysis->estimated_cost_usd,
                        'created_at' => $analysis->created_at?->toIso8601String(),
                        'finished_at' => $analysis->finished_at?->toIso8601String(),
                        // Only project-authorized viewers receive safely rendered Markdown.
                        // Never expose raw Markdown, input digest, source snippets or provider errors.
                        'result_html' => $analysis->status === PullRequestAiAnalysis::STATUS_COMPLETED
                            ? $safeMarkdown->render($analysis->result_markdown)
                            : '',
                    ];
                })->values(),
            ],
            'can_sync_files' => $request->user()->can('manageManagers', $project) && $project->archived_at === null,
            'files_current' => $filesCurrent,
            'files_count' => $filesCount,
            'files_limit' => 100,
            'files_synced_at' => $pull->files_synced_at?->toIso8601String(),
            'files' => $files->map(fn ($file): array => [
                'path' => $file->path, 'previous_path' => $file->previous_path,
                'status' => $file->status, 'additions' => $file->additions,
                'deletions' => $file->deletions, 'patch' => $file->patch,
                'patch_truncated' => $file->patch_truncated,
            ])->values(),
        ]);
    }
    /** Refresh this exact PR and its HEAD-bound files for AI without triggering DeepSeek. */
    public function syncForAi(Request $request, Project $project, int $number, RepositoryChangeRequestAiSnapshotSynchronizer $synchronizer): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        abort_if($project->archived_at !== null || $request->user()->disabled_at !== null, 403);
        $repository = RepositoryConnection::query()->where('project_id', $project->id)
            ->whereHas('connection.instance', fn ($query) => $query
                ->where('provider', 'github')->where('instance_key', GitHubInstance::key())
                ->where('base_url', GitHubInstance::BASE_URL))->firstOrFail();
        $repository->changeRequests()->where('number', $number)->firstOrFail();

        try {
            $result = $synchronizer->synchronize($repository, $number);
        } catch (\Throwable $exception) {
            // GitHub errors can include secrets or source fragments; never log raw messages.
            \Illuminate\Support\Facades\Log::warning('PR snapshot synchronization failed', [
                'project_id' => $project->id,
                'pull_number' => $number,
                'exception_type' => $exception::class,
            ]);
            throw ValidationException::withMessages([
                'sync' => app()->getLocale() === 'pl'
                    ? 'Nie udało się odświeżyć PR-a i plików. Spróbuj ponownie za chwilę.'
                    : 'Could not refresh the PR and its files. Please try again shortly.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => app()->getLocale() === 'pl'
                ? "PR i pliki zsynchronizowane ({$result['files']} plików). Możesz zlecić analizę AI."
                : "PR and files synced ({$result['files']} files). AI analysis is ready.",
        ]);
        return to_route('projects.github.pull.show', ['project' => $project, 'number' => $number]);
    }

    /** Refresh the selected PR only; no other project's data can be touched. */
    public function syncFiles(Request $request, Project $project, int $number, RepositoryChangeRequestFileSynchronizer $synchronizer): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        abort_if($project->archived_at !== null, 403);
        $repository = RepositoryConnection::query()->where('project_id', $project->id)
            ->whereHas('connection.instance', fn ($q) => $q
                ->where('provider', 'github')->where('instance_key', GitHubInstance::key())
                ->where('base_url', GitHubInstance::BASE_URL))->firstOrFail();
        abort_unless($repository->changeRequests()->where('number', $number)->exists(), 404);
        try {
            $result = $synchronizer->synchronize($repository, force: true, pullNumber: $number);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'files' => app()->getLocale() === 'pl'
                    ? 'Nie udało się pobrać zmian plików. Zsynchronizuj najpierw PR-y lub spróbuj ponownie.'
                    : 'Could not fetch file changes. Sync pull requests first or try again.',
            ]);
        }
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => app()->getLocale() === 'pl'
                ? "Zaktualizowano pliki: {$result['files']}."
                : "Updated changed files: {$result['files']}.",
        ]);
        return to_route('projects.github.pull.show', ['project' => $project, 'number' => $number]);
    }

}
