<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryChangeRequestParticipantResolver;
use App\Integrations\Vcs\RepositoryChangeRequestReviewResolver;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use App\Support\Projects\PullRequestBodyMarkdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only details of a stored GitHub pull request; does not contact GitHub. */
final class ProjectGitHubPullRequestController extends Controller
{
    public function __invoke(Request $request, Project $project, int $number, PullRequestBodyMarkdown $bodyMarkdown): Response
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
        ]);
    }
}
