<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_project_page_lists_projects_from_the_current_workspace(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        [$otherWorkspace] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $alpha = app(CreateProject::class)->handle($user, $workspace, 'Alpha App', 'APP');
        app(CreateProject::class)->handle($user, $workspace, 'Beta Project', 'BETA');
        Project::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherWorkspace->created_by,
            'name' => 'Other Workspace Project',
            'key' => 'OTHER',
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Index')
                ->has('projects.items', 2)
                ->where('projects.items.0.id', $alpha->id)
                ->where('projects.items.0.name', 'Alpha App')
                ->where('projects.can_create', true),
            );
    }

    public function test_workspace_owner_can_create_a_project_from_the_inertia_flow(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $response = $this
            ->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.store'), [
                'name' => 'Customer Portal',
                'key' => 'app',
                'description' => 'Customer-facing application.',
                'type' => ProjectType::APPLICATION->value,
                'visibility' => ProjectVisibility::PRIVATE->value,
            ]);

        $project = Project::query()->where('workspace_id', $workspace->id)->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame('APP', $project->key);
        $this->assertSame(ProjectType::APPLICATION, $project->type);
        $this->assertSame(ProjectVisibility::PRIVATE, $project->visibility);
        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
        $this->assertTrue($user->can('manageMembers', $project));
    }

    public function test_project_form_validates_required_fields_and_project_key(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->from(route('projects.index'))
            ->post(route('projects.store'), [
                'name' => '',
                'key' => 'APP-ONE',
                'type' => 999,
                'visibility' => 999,
            ])
            ->assertSessionHasErrors(['name', 'key', 'type', 'visibility'])
            ->assertRedirect(route('projects.index'));
    }

    public function test_regular_workspace_member_cannot_create_a_project_from_the_http_flow(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::MEMBER);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.store'), [
                'name' => 'Blocked Project',
                'key' => 'BLOCK',
                'type' => ProjectType::STANDARD->value,
                'visibility' => ProjectVisibility::WORKSPACE->value,
            ])
            ->assertForbidden();
    }

    public function test_project_member_sees_only_explicitly_assigned_projects(): void
    {
        [$workspace, $owner] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $member = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $workspaceProject = app(CreateProject::class)->handle(
            $owner,
            $workspace,
            'Visible Project',
            'VIS',
            ProjectType::STANDARD,
            ProjectVisibility::WORKSPACE,
        );
        $privateProject = app(CreateProject::class)->handle(
            $owner,
            $workspace,
            'Private Project',
            'PRIV',
            ProjectType::STANDARD,
            ProjectVisibility::PRIVATE,
        );

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.index'))
            ->assertRedirect();

        ProjectMember::factory()->create([
            'project_id' => $workspaceProject->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $workspaceProject->id)
                ->where('projects.can_create', false),
            );

        ProjectMember::factory()->create([
            'project_id' => $privateProject->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects.items', 2),
            );
    }

    public function test_user_can_open_an_available_project_and_the_workspace_is_synchronized(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $project = app(CreateProject::class)->handle($user, $workspace, 'Customer Portal', 'APP');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSessionHas('current_workspace_id', $workspace->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Show')
                ->where('project.id', $project->id)
                ->where('project.key', 'APP')
                ->where('project.can_manage', true),
            );
    }

    public function test_project_routes_require_authentication(): void
    {
        $this->get(route('projects.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * @return array{Workspace, User}
     */
    private function workspaceWithMember(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $user->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return [$workspace, $user];
    }
}
