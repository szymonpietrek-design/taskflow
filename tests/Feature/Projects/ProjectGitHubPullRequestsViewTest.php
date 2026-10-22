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

class ProjectGitHubPullRequestsViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_project_member_sees_pr_status_review_count_and_verified_author(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $author = $this->member($project, 'Author recognized in TaskFlow');
        UserVcsAccount::query()->create([
            'user_id' => $author->id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_user_id' => '789',
            'login' => 'renamed-author',
            'connected_at' => now(),
        ]);
        $pull = $this->pull($repository, 17, [
            'author_external_user_id' => '789',
            'author_login' => 'previous-login',
            'is_draft' => true,
            'source_branch' => 'feature/ui',
            'target_branch' => 'main',
            'body' => 'INTERNAL_BODY_NOT_FOR_INDEX',
            'web_url' => 'javascript:alert(1)',
        ]);
        $pull->reviews()->create([
            'external_review_id' => 'review-1', 'state' => 'approved',
            'reviewer_login' => 'someone',
        ]);
        $pull->reviews()->create([
            'external_review_id' => 'review-2', 'state' => 'pending',
        ]);
        $viewer = $this->member($project, 'Viewer');

        $response = $this->actingAs($viewer)->get(route('projects.github.index', $project));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/integrations/GitHub')
            ->where('can_connect', false)
            ->where('pull_requests_count', 1)
            ->has('pull_requests', 1)
            ->where('pull_requests.0.number', 17)
            ->where('pull_requests.0.title', 'PR #17')
            ->where('pull_requests.0.state', 'open')
            ->where('pull_requests.0.is_draft', true)
            ->where('pull_requests.0.author_login', 'previous-login')
            ->where('pull_requests.0.taskflow_author.id', $author->id)
            ->where('pull_requests.0.taskflow_author.name', $author->name)
            ->where('pull_requests.0.reviews_count', 1)
            ->where('pull_requests.0.web_url', 'https://github.com/example/repo/pull/17')
            ->missing('pull_requests.0.body')
            ->missing('pull_requests.0.author_external_user_id')
            ->etc());
        $this->assertStringNotContainsString('INTERNAL_BODY_NOT_FOR_INDEX', $response->getContent());
    }

    public function test_disconnected_project_has_empty_pr_list(): void
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
            'suspended_at' => null,
        ]);

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('repository', null)
                ->where('pull_requests_count', 0)
                ->has('pull_requests', 0)
                ->etc());
    }

    public function test_prs_from_other_repositories_do_not_appear(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->pull($repository, 1);
        $other = Project::factory()->create(['workspace_id' => $project->workspace_id]);
        [, $otherRepository] = $this->repository($other);
        $this->pull($otherRepository, 2);

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pull_requests_count', 1)
                ->has('pull_requests', 1)
                ->where('pull_requests.0.number', 1)
                ->etc());
    }

    public function test_merged_and_closed_states_are_preserved_for_the_ui(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->pull($repository, 1, [
            'state' => RepositoryChangeRequest::STATE_MERGED,
            'merged_at' => now(),
            'remote_updated_at' => now()->subMinute(),
        ]);
        $this->pull($repository, 2, [
            'state' => RepositoryChangeRequest::STATE_CLOSED,
            'closed_at' => now(),
            'remote_updated_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pull_requests_count', 2)
                ->where('pull_requests.0.state', 'closed')
                ->where('pull_requests.1.state', 'merged')
                ->etc());
    }

    public function test_pr_authors_not_in_project_cannot_be_identified(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $outsider = User::factory()->create(['name' => 'Not a team member']);
        UserVcsAccount::query()->create([
            'user_id' => $outsider->id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_user_id' => '789',
            'login' => 'external-user',
            'connected_at' => now(),
        ]);
        $this->pull($repository, 1, [
            'author_external_user_id' => '789',
            'author_login' => 'external-user',
        ]);
        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pull_requests.0.taskflow_author', null)
                ->where('pull_requests.0.author_login', 'external-user')
                ->etc());
    }

    public function test_list_only_contains_fifteen_latest_updated_prs(): void
    {
        [$project, $repository, $owner] = $this->repository();
        for ($i = 1; $i <= 17; $i++) {
            $this->pull($repository, $i, [
                'remote_updated_at' => now()->subMinutes(18 - $i),
            ]);
        }
        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pull_requests_count', 17)
                ->has('pull_requests', 15)
                ->where('pull_requests.0.number', 17)
                ->where('pull_requests.14.number', 3)
                ->etc());
    }

    public function test_outsider_cannot_view_prs(): void
    {
        [$project, $repository] = $this->repository();
        $this->pull($repository, 5);
        $this->actingAs(User::factory()->create())
            ->get(route('projects.github.index', $project))->assertForbidden();
    }

    /** @return array{Project, RepositoryConnection, User} */
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
            'external_connection_id' => (string) (5000 + $project->id),
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => (string) (6000 + $project->id),
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

    private function pull(RepositoryConnection $repository, int $number, array $attributes = []): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create(array_replace([
            'external_change_request_id' => (string) (10000 + $number),
            'number' => $number,
            'title' => "PR #{$number}",
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'is_draft' => false,
            'web_url' => "https://github.com/example/repo/pull/{$number}",
            'remote_updated_at' => now(),
        ], $attributes));
    }
}
