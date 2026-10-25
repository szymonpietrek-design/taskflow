<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubPullRequestReader;
use App\Integrations\GitHub\GitHubPullRequestReviewReader;
use App\Integrations\Vcs\RepositorySyncLock;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProjectGitHubActivityActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_workspace_admin_can_sync_pulls_and_return_to_pull_tab_without_advancing_sha(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $reader = Mockery::mock(GitHubPullRequestReader::class);
        $reader->shouldReceive('listPullRequests')->once()->andReturn([]);
        $this->app->instance(GitHubPullRequestReader::class, $reader);

        $this->actingAs($owner)->post(route('projects.github.pulls.sync', $project))
            ->assertRedirect(route('projects.github.index', ['project' => $project, 'tab' => 'pulls']));
        $this->assertSame(str_repeat('a', 40), $repository->fresh()->last_synced_sha);
    }

    public function test_workspace_admin_can_refresh_reviews_with_no_pulls(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $this->actingAs($owner)->post(route('projects.github.reviews.sync', $project))
            ->assertRedirect(route('projects.github.index', ['project' => $project, 'tab' => 'pulls']));
        $this->assertSame(str_repeat('a', 40), $repository->fresh()->last_synced_sha);
    }

    public function test_member_cannot_trigger_pr_or_review_sync(): void
    {
        [$project] = $this->repository();
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);
        $this->actingAs($member)->post(route('projects.github.pulls.sync', $project))->assertForbidden();
        $this->actingAs($member)->post(route('projects.github.reviews.sync', $project))->assertForbidden();
    }

    public function test_archived_project_cannot_sync(): void
    {
        [$project, , $owner] = $this->repository();
        $project->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->post(route('projects.github.pulls.sync', $project))->assertForbidden();
        $this->actingAs($owner)->post(route('projects.github.reviews.sync', $project))->assertForbidden();
    }

    public function test_api_failure_does_not_leak_exception_details_to_form_errors(): void
    {
        [$project, , $owner] = $this->repository();
        $reader = Mockery::mock(GitHubPullRequestReader::class);
        $reader->shouldReceive('listPullRequests')->once()->andThrow(new RuntimeException('SECRET_TOKEN_VALUE'));
        $this->app->instance(GitHubPullRequestReader::class, $reader);

        $response = $this->actingAs($owner)->post(route('projects.github.pulls.sync', $project));
        $response->assertSessionHasErrors(['pulls']);
        $this->assertStringNotContainsString('SECRET_TOKEN_VALUE', implode(' ', session('errors')->get('pulls')));
    }

    public function test_concurrent_repository_operation_rejects_both_manual_refreshes(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        $this->assertTrue($lock->get());
        try {
            $this->actingAs($owner)->post(route('projects.github.pulls.sync', $project))
                ->assertSessionHasErrors(['pulls']);
            $this->actingAs($owner)->post(route('projects.github.reviews.sync', $project))
                ->assertSessionHasErrors(['reviews']);
            $this->assertSame(str_repeat('a', 40), $repository->fresh()->last_synced_sha);
            $this->assertDatabaseCount('repository_change_requests', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_review_refresh_failure_does_not_expose_provider_error_to_browser(): void
    {
        [$project, $repository, $owner] = $this->repository();
        $repository->changeRequests()->create([
            'external_change_request_id' => '501', 'number' => 1,
            'title' => 'Example PR', 'web_url' => 'https://github.com/example/repo/pull/1',
            'state' => 'open', 'remote_updated_at' => now(),
        ]);
        $reader = Mockery::mock(GitHubPullRequestReviewReader::class);
        $reader->shouldReceive('reviewsForStoredPullRequests')->once()
            ->andThrow(new RuntimeException('SECRET_PROVIDER_CREDENTIAL'));
        $this->app->instance(GitHubPullRequestReviewReader::class, $reader);

        Log::spy();
        $this->actingAs($owner)->post(route('projects.github.reviews.sync', $project))
            ->assertSessionHasErrors(['reviews']);
        Log::shouldHaveReceived('warning')->once()->with(
            'github_repository_operation_failed',
            Mockery::on(fn (array $context): bool =>
                $context['project_id'] === $project->id
                && $context['operation'] === 'reviews'
                && $context['exception_class'] === RuntimeException::class
                && $context['provider_http_status'] === null
                && ! str_contains(json_encode($context), 'SECRET_PROVIDER_CREDENTIAL')),
        );
        $this->assertStringNotContainsString(
            'SECRET_PROVIDER_CREDENTIAL', implode(' ', session('errors')->get('reviews')),
        );
        $this->assertDatabaseCount('repository_change_request_reviews', 0);
        $this->assertSame(str_repeat('a', 40), $repository->fresh()->last_synced_sha);
    }

    /** @return array{Project,RepositoryConnection,User} */
    private function repository(): array
    {
        $project = Project::factory()->create();
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
            'external_connection_id' => '45001',
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => '45002',
            'full_name' => 'example/repo',
            'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('a', 40),
            'connected_at' => now(),
        ]);
        return [$project, $repository, $owner];
    }
}
