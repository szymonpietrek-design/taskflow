<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\UserVcsAccount;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectGitHubPullRequestDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_project_member_can_view_pr_details_merged_by_and_submitted_reviews(): void
    {
        [$project, $repository] = $this->repository();
        $viewer = $this->member($project, 'Team member');
        $author = $this->member($project, 'PR author');
        $merger = $this->member($project, 'Person merging');
        $reviewer = $this->member($project, 'Code reviewer');
        $this->account($author, '11');
        $this->account($merger, '22');
        $this->account($reviewer, '33');
        $pull = $this->pull($repository, 7, [
            'state' => RepositoryChangeRequest::STATE_MERGED,
            'body' => "What changed\n- feature",
            'author_external_user_id' => '11',
            'author_login' => 'old-author-login',
            'merged_by_external_user_id' => '22',
            'merged_by_login' => 'merger-login',
            'merged_at' => now(),
            'head_sha' => str_repeat('f', 40),
            'web_url' => 'javascript:alert(1)',
        ]);
        $pull->reviews()->create([
            'external_review_id' => '901', 'state' => 'approved',
            'reviewer_external_user_id' => '33', 'reviewer_login' => 'old-reviewer',
            'body' => 'Looks good', 'submitted_at' => now(),
        ]);
        $pull->reviews()->create([
            'external_review_id' => '902', 'state' => 'pending',
            'reviewer_external_user_id' => '33', 'reviewer_login' => 'private-draft',
            'body' => 'SECRET_NOT_SUBMITTED',
        ]);

        $response = $this->actingAs($viewer)->get(route('projects.github.pull.show', [$project, 7]));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/integrations/GitHubPullRequest')
            ->where('pull_request.number', 7)
            ->where('pull_request.title', 'Change #7')
            ->where('pull_request.state', 'merged')
            ->where('pull_request.body_html', fn ($html) => is_string($html) && str_contains($html, 'What changed'))
            ->where('pull_request.web_url', 'https://github.com/example/repo/pull/7')
            ->where('pull_request.author.taskflow_user.name', 'PR author')
            ->where('pull_request.merged_by.taskflow_user.name', 'Person merging')
            ->where('reviews_count', 1)
            ->has('reviews', 1)
            ->where('reviews.0.state', 'approved')
            ->where('reviews.0.taskflow_reviewer.name', 'Code reviewer')
            ->missing('reviews.0.external_review_id')
            ->etc());
        $this->assertStringNotContainsString('SECRET_NOT_SUBMITTED', $response->getContent());
        $this->assertStringNotContainsString('javascript:alert', $response->getContent());
    }

    public function test_untrusted_pull_request_body_is_sanitized_before_inertia(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->pull($repository, 19, [
            'body' => '<details open><summary>Changelog</summary><script>alert(1)</script><a href="javascript:alert(2)" onclick="evil()">unsafe</a></details>',
        ]);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 19]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pull_request.body_html', fn ($html) => is_string($html)
                    && str_contains($html, '<details>')
                    && str_contains($html, '<summary>Changelog</summary>')
                    && ! str_contains($html, '<script')
                    && ! str_contains($html, 'onclick')
                    && ! str_contains($html, 'javascript:')
                    && ! str_contains($html, ' open'))
                ->missing('pull_request.body')
                ->etc());
    }

    public function test_user_outside_project_cannot_open_pull_request_details(): void
    {
        [$project, $repository] = $this->repository();
        $this->pull($repository, 4);
        $this->actingAs(User::factory()->create())
            ->get(route('projects.github.pull.show', [$project, 4]))->assertForbidden();
    }

    public function test_pull_request_from_another_project_is_not_visible(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $otherProject = Project::factory()->create(['workspace_id' => $project->workspace_id]);
        [, $otherRepo] = $this->repository($otherProject);
        $this->pull($otherRepo, 20);
        $this->pull($repository, 19);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 20]))->assertNotFound();
    }

    public function test_project_without_repository_or_unknown_pr_number_returns_404(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 777]))->assertNotFound();
        $withoutRepository = Project::factory()->create(['workspace_id' => $project->workspace_id]);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$withoutRepository, 777]))->assertNotFound();
    }

    public function test_unlinked_and_outside_team_reviewers_are_not_attributed(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $outsider = User::factory()->create(['name' => 'Outsider']);
        $this->account($outsider, '555');
        $pull = $this->pull($repository, 9);
        $pull->reviews()->create([
            'external_review_id' => 'r-1', 'state' => 'commented',
            'reviewer_external_user_id' => '555', 'reviewer_login' => 'outside',
            'body' => 'Review comment',
        ]);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 9]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reviews.0.reviewer_login', 'outside')
                ->where('reviews.0.taskflow_reviewer', null)
                ->etc());
    }

    public function test_nonmerged_pr_does_not_show_stale_merger_information(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->pull($repository, 11, [
            'state' => RepositoryChangeRequest::STATE_CLOSED,
            'merged_by_external_user_id' => '22',
            'merged_by_login' => 'stale-merger',
            'closed_at' => now(),
        ]);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 11]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pull_request.merged_by', null)
                ->etc());
    }

    public function test_only_fifty_latest_submitted_reviews_are_returned(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $pull = $this->pull($repository, 15);
        for ($i = 1; $i <= 52; $i++) {
            $pull->reviews()->create([
                'external_review_id' => (string) $i,
                'state' => 'commented',
                'reviewer_login' => 'reviewer',
                'body' => "Review {$i}",
                'submitted_at' => now()->addMinutes($i),
            ]);
        }
        $pull->reviews()->create([
            'external_review_id' => 'private', 'state' => 'pending',
            'body' => 'SECRET_DRAFT',
        ]);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 15]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reviews_count', 52)
                ->where('reviews_limit', 50)
                ->has('reviews', 50)
                ->where('reviews.0.body', 'Review 52')
                ->where('reviews.49.body', 'Review 3')
                ->etc());
    }

    public function test_back_to_pull_requests_query_selects_pull_tab_on_index(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->pull($repository, 6);
        $this->actingAs($owner)
            ->get(route('projects.github.index', $project).'?tab=pulls')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('initial_tab', 'pulls')
                ->where('pull_requests_count', 1)
                ->etc());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [$project, $repository] = $this->repository();
        $this->pull($repository, 2);
        $this->get(route('projects.github.pull.show', [$project, 2]))->assertRedirect(route('login'));
    }

    /** @return array{Project,RepositoryConnection,User} */
    private function repository(?Project $project = null): array
    {
        $project ??= Project::factory()->create();
        $owner = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
            'suspended_at' => null,
        ]);
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => (string) (9000 + $project->id),
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => (string) (10000 + $project->id),
            'full_name' => 'example/repo',
            'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('a', 40),
            'connected_at' => now(),
        ]);
        return [$project, $repository, $owner];
    }

    private function member(Project $project, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);
        return $user;
    }

    private function account(User $user, string $githubId): void
    {
        UserVcsAccount::query()->create([
            'user_id' => $user->id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_user_id' => $githubId,
            'login' => 'previous-login',
            'connected_at' => now(),
        ]);
    }

    private function pull(RepositoryConnection $repository, int $number, array $attributes = []): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create(array_replace([
            'external_change_request_id' => (string) (20000 + $number),
            'number' => $number,
            'title' => "Change #{$number}",
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'is_draft' => false,
            'web_url' => "https://github.com/example/repo/pull/{$number}",
            'remote_updated_at' => now(),
        ], $attributes));
    }
}
