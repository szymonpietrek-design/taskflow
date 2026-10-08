<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_members_can_view_workspace_visible_projects(): void
    {
        [$project, $user] = $this->projectForWorkspaceMember(
            WorkspaceRole::MEMBER,
            ProjectVisibility::WORKSPACE,
        );

        $this->assertTrue($user->can('view', $project));
        $this->assertFalse($user->can('update', $project));
        $this->assertFalse($user->can('delete', $project));
        $this->assertFalse($user->can('archive', $project));
        $this->assertFalse($user->can('manageMembers', $project));
    }

    public function test_private_projects_are_hidden_from_regular_workspace_members(): void
    {
        [$project, $user] = $this->projectForWorkspaceMember(
            WorkspaceRole::MEMBER,
            ProjectVisibility::PRIVATE,
        );

        $this->assertFalse($user->can('view', $project));
    }

    public function test_private_project_members_can_view_the_project(): void
    {
        [$project, $user] = $this->projectForWorkspaceMember(
            WorkspaceRole::MEMBER,
            ProjectVisibility::PRIVATE,
        );

        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->assertTrue($user->can('view', $project));
        $this->assertFalse($user->can('update', $project));
        $this->assertFalse($user->can('manageMembers', $project));
    }

    public function test_project_managers_can_manage_the_project(): void
    {
        [$project, $user] = $this->projectForWorkspaceMember(
            WorkspaceRole::MEMBER,
            ProjectVisibility::PRIVATE,
        );

        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $this->assertTrue($user->can('view', $project));
        $this->assertTrue($user->can('update', $project));
        $this->assertTrue($user->can('delete', $project));
        $this->assertTrue($user->can('archive', $project));
        $this->assertTrue($user->can('manageMembers', $project));
    }

    public function test_workspace_admins_can_manage_all_projects_in_the_workspace(): void
    {
        [$project, $user] = $this->projectForWorkspaceMember(
            WorkspaceRole::ADMIN,
            ProjectVisibility::PRIVATE,
        );

        $this->assertTrue($user->can('view', $project));
        $this->assertTrue($user->can('update', $project));
        $this->assertTrue($user->can('delete', $project));
        $this->assertTrue($user->can('archive', $project));
        $this->assertTrue($user->can('manageMembers', $project));
    }

    public function test_workspace_owners_can_manage_all_projects_in_the_workspace(): void
    {
        [$project, $user] = $this->projectForWorkspaceMember(
            WorkspaceRole::OWNER,
            ProjectVisibility::PRIVATE,
        );

        $this->assertTrue($user->can('view', $project));
        $this->assertTrue($user->can('update', $project));
        $this->assertTrue($user->can('manageMembers', $project));
    }

    public function test_users_outside_the_workspace_cannot_access_the_project(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($user->can('view', $project));
        $this->assertFalse($user->can('update', $project));
        $this->assertFalse($user->can('delete', $project));
        $this->assertFalse($user->can('archive', $project));
        $this->assertFalse($user->can('manageMembers', $project));
    }

    public function test_only_workspace_owners_and_admins_can_create_projects(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = $this->addWorkspaceMember($workspace, WorkspaceRole::OWNER);
        $admin = $this->addWorkspaceMember($workspace, WorkspaceRole::ADMIN);
        $member = $this->addWorkspaceMember($workspace, WorkspaceRole::MEMBER);
        $outsider = User::factory()->create();

        $this->assertTrue($owner->can('create', [Project::class, $workspace]));
        $this->assertTrue($admin->can('create', [Project::class, $workspace]));
        $this->assertFalse($member->can('create', [Project::class, $workspace]));
        $this->assertFalse($outsider->can('create', [Project::class, $workspace]));
    }

    /**
     * @return array{Project, User}
     */
    private function projectForWorkspaceMember(
        WorkspaceRole $workspaceRole,
        ProjectVisibility $visibility,
    ): array {
        $workspace = Workspace::factory()->create();
        $user = $this->addWorkspaceMember($workspace, $workspaceRole);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $workspace->created_by,
            'visibility' => $visibility,
        ]);

        return [$project, $user];
    }

    private function addWorkspaceMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }
}
