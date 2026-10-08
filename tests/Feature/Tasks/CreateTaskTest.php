<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\TaskType;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_provisions_the_default_task_workflow(): void
    {
        [$workspace, $owner] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $project = app(CreateProject::class)->handle($owner, $workspace, 'Customer Portal', 'APP');

        $this->assertSame(4, $project->taskStatuses()->count());
        $this->assertSame(
            ['Todo', 'In Progress', 'Done', 'Canceled'],
            $project->taskStatuses()->orderBy('position')->pluck('name')->all(),
        );
    }

    public function test_it_creates_a_task_with_defaults_and_the_first_project_number(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $task = app(CreateTask::class)->handle($owner, $project, 'Build dashboard');

        $this->assertSame(1, $task->number);
        $this->assertSame('APP-1', $task->reference());
        $this->assertSame(TaskType::TASK, $task->type);
        $this->assertSame(TaskPriority::NORMAL, $task->priority);
        $this->assertSame(TaskStatusCategory::TODO, $task->status->category);
        $this->assertSame(1000, $task->position);
        $this->assertNull($task->completed_at);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'number' => 1,
            'title' => 'Build dashboard',
            'created_by' => $owner->id,
        ]);
    }

    public function test_task_numbers_are_sequential_inside_the_project(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $action = app(CreateTask::class);

        $first = $action->handle($owner, $project, 'First task');
        $second = $action->handle($owner, $project, 'Second task');

        $this->assertSame('APP-1', $first->reference());
        $this->assertSame('APP-2', $second->reference());
        $this->assertSame(2, $project->fresh()->task_sequence);
        $this->assertSame(2000, $second->position);
    }

    public function test_existing_project_without_statuses_gets_the_default_workflow_lazily(): void
    {
        [$workspace, $owner] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'key' => 'LEG',
        ]);

        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $this->assertSame(0, $project->taskStatuses()->count());

        $task = app(CreateTask::class)->handle($owner, $project, 'Legacy project task');

        $this->assertSame(4, $project->taskStatuses()->count());
        $this->assertSame(TaskStatusCategory::TODO, $task->status->category);
    }

    public function test_it_can_create_a_single_level_subtask(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $action = app(CreateTask::class);
        $parent = $action->handle($owner, $project, 'Parent task');

        $subtask = $action->handle(
            $owner,
            $project,
            'Child task',
            parent: $parent,
        );

        $this->assertSame($parent->id, $subtask->parent_id);
        $this->assertSame(1, $parent->subtasks()->count());
    }

    public function test_it_rejects_a_subtask_of_a_subtask(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $action = app(CreateTask::class);
        $parent = $action->handle($owner, $project, 'Parent task');
        $child = $action->handle($owner, $project, 'Child task', parent: $parent);

        $this->expectException(InvalidArgumentException::class);

        $action->handle($owner, $project, 'Nested child', parent: $child);
    }

    public function test_it_rejects_a_parent_from_another_project(): void
    {
        [$firstProject, $owner] = $this->projectWithOwner('APP');
        $secondProject = app(CreateProject::class)->handle(
            $owner,
            $firstProject->workspace,
            'Second Project',
            'WEB',
        );
        $action = app(CreateTask::class);
        $parent = $action->handle($owner, $secondProject, 'Foreign parent');

        $this->expectException(InvalidArgumentException::class);

        $action->handle($owner, $firstProject, 'Invalid child', parent: $parent);
    }

    public function test_it_rejects_a_status_from_another_project(): void
    {
        [$firstProject, $owner] = $this->projectWithOwner('APP');
        $secondProject = app(CreateProject::class)->handle(
            $owner,
            $firstProject->workspace,
            'Second Project',
            'WEB',
        );
        $foreignStatus = $secondProject->taskStatuses()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        app(CreateTask::class)->handle($owner, $firstProject, 'Invalid status task', status: $foreignStatus);
    }

    public function test_assignee_must_be_a_project_contributor(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $viewer = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(CreateTask::class)->handle($owner, $project, 'Assigned task', assignee: $viewer);
    }

    public function test_project_member_can_be_assigned_to_a_task(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $member = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $task = app(CreateTask::class)->handle($owner, $project, 'Assigned task', assignee: $member);

        $this->assertSame($member->id, $task->assignee_id);
    }

    public function test_done_status_marks_a_new_task_as_completed(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $done = $project->taskStatuses()
            ->where('category', TaskStatusCategory::DONE->value)
            ->firstOrFail();

        $task = app(CreateTask::class)->handle($owner, $project, 'Already done', status: $done);

        $this->assertNotNull($task->completed_at);
    }

    public function test_it_rejects_an_empty_task_title(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->expectException(InvalidArgumentException::class);

        app(CreateTask::class)->handle($owner, $project, '   ');
    }

    /**
     * @return array{Project, User}
     */
    private function projectWithOwner(string $key = 'APP'): array
    {
        [$workspace, $owner] = $this->workspaceWithMember(WorkspaceRole::OWNER);
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Task Project', $key);

        return [$project, $owner];
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
