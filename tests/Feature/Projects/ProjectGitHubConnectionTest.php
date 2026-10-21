<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubAppClient;
use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProjectGitHubConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Inertia feature tests should not depend on a prebuilt Vite manifest.
        $this->withoutVite();
    }

    private const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_owner_can_link_repository_and_store_installation_in_database_without_importing_history(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $this->mockGithubInspection();

        $this->actingAs($owner)->post(route('projects.github.store', $project), [
            'installation_id' => 456, 'repository' => 'example/api',
        ])->assertRedirect(route('projects.github.index', $project));

        $this->assertDatabaseHas('github_installations', [
            'workspace_id' => $project->workspace_id,
            'installation_id' => 456,
            'github_account_id' => 78,
            'account_login' => 'example',
        ]);
        $this->assertDatabaseHas('github_repositories', [
            'project_id' => $project->id,
            'full_name' => 'example/api',
            'start_sha' => self::HEAD,
            'last_synced_sha' => self::HEAD,
            'last_synced_at' => null,
        ]);
        $this->assertSame(456, GitHubRepository::query()->firstOrFail()->installation->installation_id);
    }

    public function test_second_project_reuses_workspace_installation(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $secondProject = Project::factory()->create(['workspace_id' => $project->workspace_id]);
        $this->mockGithubInspection(2);

        foreach ([$project, $secondProject] as $item) {
            $this->actingAs($owner)->post(route('projects.github.store', $item), [
                'installation_id' => 456, 'repository' => 'example/api',
            ])->assertRedirect(route('projects.github.index', $item));
        }

        $this->assertDatabaseCount('github_installations', 1);
        $this->assertDatabaseCount('github_repositories', 2);
    }

    public function test_duplicate_connection_never_resets_last_synced_sha(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $this->createLinkedRepository($project);
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldNotReceive('inspectRepository');
        $github->shouldNotReceive('inspectInstallation');
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($owner)->from(route('projects.github.index', $project))
            ->post(route('projects.github.store', $project), [
                'installation_id' => 456, 'repository' => 'example/api',
            ])->assertRedirect(route('projects.github.index', $project))
            ->assertSessionHasErrors('repository');

        $this->assertSame(str_repeat('b', 40), GitHubRepository::query()->firstOrFail()->last_synced_sha);
    }

    public function test_an_installation_registered_to_another_workspace_cannot_be_reused(): void
    {
        [$first] = $this->projectWithOwner();
        $installation = $this->createInstallation($first);
        [$second, $secondOwner] = $this->projectWithOwner();
        $this->assertNotEquals($first->workspace_id, $second->workspace_id);
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldNotReceive('inspectInstallation');
        $github->shouldNotReceive('inspectRepository');
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($secondOwner)->from(route('projects.github.index', $second))
            ->post(route('projects.github.store', $second), [
                'installation_id' => $installation->installation_id, 'repository' => 'example/api',
            ])->assertSessionHasErrors('installation_id');
        $this->assertDatabaseCount('github_repositories', 0);
    }

    public function test_workspace_owner_without_system_admin_role_cannot_register_an_unapproved_installation(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $owner->is_system_admin = false;
        $owner->save();
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldNotReceive('inspectInstallation');
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($owner)->from(route('projects.github.index', $project))
            ->post(route('projects.github.store', $project), [
                'installation_id' => 456, 'repository' => 'example/api',
            ])->assertSessionHasErrors('installation_id');
        $this->assertDatabaseCount('github_installations', 0);
    }

    public function test_project_member_can_view_connection_but_cannot_change_it(): void
    {
        [$project] = $this->projectWithOwner();
        $this->createLinkedRepository($project);
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($member)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('projects/integrations/GitHub')
                ->where('can_connect', false)
                ->where('repository.full_name', 'example/api')
                ->has('installations', 0)->etc());
        $this->actingAs($member)->post(route('projects.github.store', $project), [
            'installation_id' => 456, 'repository' => 'example/api',
        ])->assertForbidden();
        $this->actingAs($member)->delete(route('projects.github.destroy', $project))->assertForbidden();
    }

    public function test_project_manager_cannot_change_installation(): void
    {
        [$project] = $this->projectWithOwner();
        $manager = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $this->actingAs($manager)->post(route('projects.github.store', $project), [
            'installation_id' => 456, 'repository' => 'example/api',
        ])->assertForbidden();
    }

    public function test_outsider_cannot_read_other_projects_github_connection(): void
    {
        [$project] = $this->projectWithOwner();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('projects.github.index', $project))->assertForbidden();
    }

    public function test_owner_can_disconnect_repository_without_removing_installation(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $this->createLinkedRepository($project);

        $this->actingAs($owner)->delete(route('projects.github.destroy', $project))
            ->assertRedirect(route('projects.github.index', $project));
        $this->assertDatabaseCount('github_repositories', 0);
        $this->assertDatabaseCount('github_installations', 1);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_missing_github_app_configuration_blocks_connection(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->actingAs($owner)->from(route('projects.github.index', $project))
            ->post(route('projects.github.store', $project), [
                'installation_id' => 456, 'repository' => 'example/api',
            ])->assertSessionHasErrors('installation_id');
        $this->assertDatabaseCount('github_installations', 0);
    }

    public function test_repository_owner_must_match_installation_account(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldReceive('configured')->andReturn(true);
        $github->shouldReceive('inspectInstallation')->andReturn([
            'installation_id' => 456, 'github_account_id' => 78,
            'account_login' => 'another-org', 'account_type' => 'Organization',
        ]);
        $github->shouldReceive('inspectRepository')->andReturn([
            'github_repository_id' => 123, 'full_name' => 'example/api',
            'default_branch' => 'main', 'head_sha' => self::HEAD,
        ]);
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($owner)->from(route('projects.github.index', $project))
            ->post(route('projects.github.store', $project), [
                'installation_id' => 456, 'repository' => 'example/api',
            ])->assertSessionHasErrors('repository');
        $this->assertDatabaseCount('github_repositories', 0);
    }

    public function test_github_api_error_does_not_create_incomplete_connection(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldReceive('configured')->andReturn(true);
        $github->shouldReceive('inspectInstallation')->andThrow(new RuntimeException('API unavailable'));
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($owner)->from(route('projects.github.index', $project))
            ->post(route('projects.github.store', $project), [
                'installation_id' => 456, 'repository' => 'example/api',
            ])->assertSessionHasErrors('repository');
        $this->assertDatabaseCount('github_repositories', 0);
        $this->assertDatabaseCount('github_installations', 0);
    }

    /** @return array{Project, User} */
    private function projectWithOwner(): array
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create(['is_system_admin' => true]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
            'suspended_at' => null,
        ]);

        return [$project, $owner];
    }

    private function createInstallation(Project $project): GitHubInstallation
    {
        return GitHubInstallation::query()->create([
            'workspace_id' => $project->workspace_id,
            'installation_id' => 456,
            'github_account_id' => 78,
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => now(),
        ]);
    }

    private function createLinkedRepository(Project $project): GitHubRepository
    {
        $installation = $this->createInstallation($project);

        return GitHubRepository::query()->create([
            'project_id' => $project->id,
            'github_installation_id' => $installation->id,
            'github_repository_id' => 123,
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => self::HEAD,
            'last_synced_sha' => str_repeat('b', 40),
            'connected_at' => now(),
        ]);
    }

    private function mockGithubInspection(int $times = 1): void
    {
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldReceive('configured')->times($times)->andReturn(true);
        $github->shouldReceive('inspectInstallation')->times($times)->with(456)->andReturn([
            'installation_id' => 456, 'github_account_id' => 78,
            'account_login' => 'example', 'account_type' => 'Organization',
        ]);
        $github->shouldReceive('inspectRepository')->times($times)->with(456, 'example/api')->andReturn([
            'github_repository_id' => 123,
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'head_sha' => self::HEAD,
        ]);
        $this->app->instance(GitHubAppClient::class, $github);
    }
}
