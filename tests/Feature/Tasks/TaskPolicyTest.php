<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_view_workspace_project_tasks_but_cannot_contribute_without_project_membership(): void
    {
        [$project, $owner] = $this->projectWithOwner(ProjectVisibility::WORKSPACE);
        $task = app(CreateTask::class)->handle($owner, $project, 'Visible task');
        $viewer = $this->addWorkspaceMember($project->workspace, WorkspaceRole::MEMBER);

        $this->assertTrue($viewer->can('view', $task));
        $this->assertFalse($viewer->can('create', [Task::class, $project]));
        $this->assertFalse($viewer->can('update', $task));
        $this->assertFalse($viewer->can('delete', $task));
    }

    public function test_project_member_can_create_and_update_tasks(): void
    {
        [$project] = $this->projectWithOwner();
        $member = $this->addWorkspaceMember($project->workspace, WorkspaceRole::MEMBER);

        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $member->id,
            'status_id' => $project->taskStatuses()->firstOrFail()->id,
        ]);

        $this->assertTrue($member->can('create', [Task::class, $project]));
        $this->assertTrue($member->can('update', $task));
        $this->assertFalse($member->can('delete', $task));
    }

    public function test_project_manager_can_delete_tasks(): void
    {
        [$project, $manager] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($manager, $project, 'Managed task');

        $this->assertTrue($manager->can('delete', $task));
    }

    public function test_workspace_admin_can_manage_tasks_without_project_membership(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Admin task');
        $admin = $this->addWorkspaceMember($project->workspace, WorkspaceRole::ADMIN);

        $this->assertTrue($admin->can('view', $task));
        $this->assertTrue($admin->can('create', [Task::class, $project]));
        $this->assertTrue($admin->can('update', $task));
        $this->assertTrue($admin->can('delete', $task));
    }

    public function test_private_project_member_can_view_and_contribute(): void
    {
        [$project] = $this->projectWithOwner(ProjectVisibility::PRIVATE);
        $member = $this->addWorkspaceMember($project->workspace, WorkspaceRole::MEMBER);

        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $member->id,
            'status_id' => $project->taskStatuses()->firstOrFail()->id,
        ]);

        $this->assertTrue($member->can('view', $task));
        $this->assertTrue($member->can('update', $task));
    }

    public function test_user_outside_workspace_cannot_access_tasks(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Protected task');
        $outsider = User::factory()->create();

        $this->assertFalse($outsider->can('view', $task));
        $this->assertFalse($outsider->can('create', [Task::class, $project]));
        $this->assertFalse($outsider->can('update', $task));
        $this->assertFalse($outsider->can('delete', $task));
    }

    /**
     * @return array{Project, User}
     */
    private function projectWithOwner(ProjectVisibility $visibility = ProjectVisibility::WORKSPACE): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $project = app(CreateProject::class)->handle(
            $owner,
            $workspace,
            'Task Project',
            'APP',
            visibility: $visibility,
        );

        return [$project, $owner];
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
