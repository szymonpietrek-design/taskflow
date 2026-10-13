<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_can_create_a_project_and_becomes_its_manager(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $project = app(CreateProject::class)->handle(
            $user,
            $workspace,
            'Customer Portal',
            'app',
            ProjectType::APPLICATION,
            ProjectVisibility::PRIVATE,
            'Customer-facing application.',
        );

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Customer Portal',
            'slug' => 'customer-portal',
            'key' => 'APP',
            'description' => 'Customer-facing application.',
            'type' => ProjectType::APPLICATION->value,
            'visibility' => ProjectVisibility::PRIVATE->value,
            'task_sequence' => 0,
        ]);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER->value,
        ]);

        $this->assertDatabaseCount('task_statuses', 4);
        $this->assertDatabaseHas('task_statuses', [
            'project_id' => $project->id,
            'name' => 'Todo',
            'slug' => 'todo',
            'category' => TaskStatusCategory::TODO->value,
            'position' => 1000,
        ]);
        $this->assertDatabaseHas('task_statuses', [
            'project_id' => $project->id,
            'name' => 'In Progress',
            'category' => TaskStatusCategory::IN_PROGRESS->value,
        ]);
        $this->assertDatabaseHas('task_statuses', [
            'project_id' => $project->id,
            'name' => 'Done',
            'category' => TaskStatusCategory::DONE->value,
        ]);
        $this->assertDatabaseHas('task_statuses', [
            'project_id' => $project->id,
            'name' => 'Canceled',
            'category' => TaskStatusCategory::CANCELED->value,
        ]);
    }

    public function test_workspace_admin_can_create_a_project(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::ADMIN);

        $project = app(CreateProject::class)->handle($user, $workspace, 'Internal Tools', 'IT');

        $this->assertSame($workspace->id, $project->workspace_id);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MANAGER->value,
        ]);
    }

    public function test_regular_workspace_member_cannot_create_a_project(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::MEMBER);

        $this->expectException(AuthorizationException::class);

        app(CreateProject::class)->handle($user, $workspace, 'Blocked Project', 'BP');
    }

    public function test_user_outside_the_workspace_cannot_create_a_project(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(CreateProject::class)->handle($user, $workspace, 'Blocked Project', 'BP');
    }

    public function test_it_generates_a_unique_slug_inside_the_workspace(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $action = app(CreateProject::class);

        $first = $action->handle($user, $workspace, 'Customer Portal', 'APP');
        $second = $action->handle($user, $workspace, 'Customer Portal', 'WEB');

        $this->assertSame('customer-portal', $first->slug);
        $this->assertSame('customer-portal-2', $second->slug);
    }

    public function test_project_key_must_be_unique_inside_the_workspace(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $action = app(CreateProject::class);

        $action->handle($user, $workspace, 'Customer Portal', 'APP');

        $this->expectException(InvalidArgumentException::class);

        $action->handle($user, $workspace, 'Another Project', 'app');
    }

    public function test_the_same_project_key_can_be_used_in_another_workspace(): void
    {
        [$firstWorkspace, $firstUser] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        [$secondWorkspace, $secondUser] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $action = app(CreateProject::class);

        $first = $action->handle($firstUser, $firstWorkspace, 'First App', 'APP');
        $second = $action->handle($secondUser, $secondWorkspace, 'Second App', 'APP');

        $this->assertSame('APP', $first->key);
        $this->assertSame('APP', $second->key);
    }

    public function test_it_rejects_an_empty_project_name(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $this->expectException(InvalidArgumentException::class);

        app(CreateProject::class)->handle($user, $workspace, '   ', 'APP');
    }

    public function test_it_rejects_an_invalid_project_key(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $this->expectException(InvalidArgumentException::class);

        app(CreateProject::class)->handle($user, $workspace, 'Customer Portal', 'APP-ONE');
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
