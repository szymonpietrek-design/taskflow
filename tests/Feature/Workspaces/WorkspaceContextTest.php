<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_workspace_is_shared_on_authenticated_inertia_pages(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'Alpha Team');
        $second = app(CreateWorkspace::class)->handle($user, 'Beta Team');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $second->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('workspace.items', 2)
                ->where('workspace.current.id', $second->id)
                ->where('workspace.current.name', 'Beta Team')
                ->where('workspace.items.0.id', $first->id)
                ->where('workspace.items.1.id', $second->id),
            );
    }

    public function test_invalid_current_workspace_falls_back_to_first_available_workspace(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'Alpha Team');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => 999999])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('current_workspace_id', $first->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current.id', $first->id),
            );
    }

    public function test_suspended_workspace_admin_with_old_project_membership_is_not_shown_in_navigation(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $user->id,
            'role' => WorkspaceRole::ADMIN, 'suspended_at' => now(),
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id]);
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspace.items', 0)
                ->has('projects.items', 0));
    }

    public function test_archived_only_regular_project_membership_does_not_reveal_workspace_name(): void
    {
        $user = User::factory()->create();
        $hiddenWorkspace = Workspace::factory()->create(['name' => 'Archived-only workspace']);
        $managerWorkspace = Workspace::factory()->create(['name' => 'Manager workspace']);
        $hiddenProject = Project::factory()->create([
            'workspace_id' => $hiddenWorkspace->id, 'archived_at' => now(),
        ]);
        $managedProject = Project::factory()->create([
            'workspace_id' => $managerWorkspace->id, 'archived_at' => now(),
        ]);
        ProjectMember::factory()->create([
            'project_id' => $hiddenProject->id, 'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $managedProject->id, 'user_id' => $user->id,
            'role' => ProjectRole::MANAGER,
        ]);

        // Archived regular members cannot read the project. Archived managers can.
        $this->assertFalse($user->can('view', $hiddenProject));
        $this->assertTrue($user->can('view', $managedProject));

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $hiddenWorkspace->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('current_workspace_id', $managerWorkspace->id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspace.items', 1)
                ->where('workspace.items.0.id', $managerWorkspace->id)
                ->where('workspace.current.id', $managerWorkspace->id)
                ->has('projects.items', 0));
    }

    public function test_authenticated_user_without_workspaces_receives_an_empty_workspace_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current', null)
                ->has('workspace.items', 0),
            );
    }
}
