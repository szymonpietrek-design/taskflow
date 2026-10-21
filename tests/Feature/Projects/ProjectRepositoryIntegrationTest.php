<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubAppClient;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class ProjectRepositoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_and_non_member_cannot_view_repository_integrations(): void
    {
        $project = Project::factory()->create();
        $this->get(route('projects.repository.index', $project))->assertRedirect(route('login'));

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('projects.repository.index', $project))->assertForbidden();
    }

    public function test_unconnected_project_offers_only_github_as_available_provider(): void
    {
        [$project, $owner] = $this->ownedProject();
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldReceive('configured')->once()->andReturn(false);
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($owner)->get(route('projects.repository.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('projects/integrations/Repository')
                ->where('repository', null)
                ->where('can_manage', true)
                ->where('providers.0.key', 'github')
                ->where('providers.0.available', true)
                ->where('providers.0.configured', false)
                ->where('providers.1.key', 'gitlab')
                ->where('providers.1.available', false)
                ->where('providers.2.key', 'gitea')
                ->where('providers.2.available', false)
                ->etc());
    }

    public function test_connected_repository_summary_is_read_from_shared_vcs_tables_without_secrets(): void
    {
        [$project, $owner] = $this->ownedProject();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '456',
            'external_account_id' => '78',
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => now(),
        ]);
        RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => '123',
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('b', 40),
            'connected_at' => now(),
        ]);

        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);
        $this->actingAs($member)->get(route('projects.repository.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('projects/integrations/Repository')
                ->where('can_manage', false)
                ->where('repository.provider', 'github')
                ->where('repository.full_name', 'example/api')
                ->where('repository.default_branch', 'main')
                ->where('repository.commits_count', 0)
                ->missing('repository.start_sha')
                ->missing('repository.external_connection_id')
                ->missing('repository.access_token')
                ->etc());
    }

    public function test_archived_project_is_read_only_for_its_manager(): void
    {
        [$project] = $this->ownedProject();
        $manager = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);
        $project->update(['archived_at' => now()]);

        $this->actingAs($manager)->get(route('projects.repository.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('project.is_archived', true)
                ->where('can_manage', false)
                ->etc());
    }

    /** @return array{Project, User} */
    private function ownedProject(): array
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
}
