<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_are_shared_on_authenticated_inertia_pages(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $project = app(CreateProject::class)->handle($user, $workspace, 'Customer Portal', 'APP');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $project->id)
                ->where('projects.items.0.key', 'APP')
                ->where('projects.can_create', true),
            );
    }

    public function test_archived_projects_are_not_shared_in_project_navigation(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        app(CreateProject::class)->handle($user, $workspace, 'Active Project', 'ACTIVE');
        $archived = app(CreateProject::class)->handle($user, $workspace, 'Archived Project', 'ARCH');
        $archived->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects.items', 1)
                ->where('projects.items.0.name', 'Active Project'),
            );
    }

    public function test_project_only_collaborator_sees_only_readable_active_projects_in_sidebar(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $active = Project::factory()->create(['workspace_id' => $workspace->id]);
        $archived = Project::factory()->create(['workspace_id' => $workspace->id, 'archived_at' => now()]);
        Project::factory()->create(['workspace_id' => $workspace->id]); // no access
        foreach ([$active, $archived] as $project) {
            ProjectMember::factory()->create([
                'project_id' => $project->id, 'user_id' => $user->id,
                'role' => ProjectRole::MEMBER,
            ]);
        }

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspace.items', 1)
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $active->id)
                ->where('projects.can_create', false));
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
