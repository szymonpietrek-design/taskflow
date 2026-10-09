<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\ChangeTaskStatus;
use App\Actions\Tasks\CreateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\TaskType;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskSubtaskDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_parent_details_show_subtasks_and_completed_progress(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $parent = app(CreateTask::class)->handle($owner, $project, 'Ship release');
        $todo = app(CreateTask::class)->handle($owner, $project, 'Write docs', parent: $parent);
        $done = app(CreateTask::class)->handle($owner, $project, 'Run checks', parent: $parent);
        $doneStatus = $project->taskStatuses()->where('category', TaskStatusCategory::DONE->value)->firstOrFail();
        app(ChangeTaskStatus::class)->handle($owner, $done, $doneStatus);

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $parent]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('can_create_subtask', true)
                ->where('task.subtask_progress.total', 2)
                ->where('task.subtask_progress.done', 1)
                ->has('task.subtasks', 2)
                ->where('task.subtasks.0.id', $todo->id)
                ->where('task.subtasks.0.status.category', 'todo')
                ->where('task.subtasks.1.id', $done->id)
                ->where('task.subtasks.1.status.category', 'done'));
    }

    public function test_child_details_link_to_parent_and_do_not_offer_nested_creation(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $parent = app(CreateTask::class)->handle($owner, $project, 'Parent');
        $child = app(CreateTask::class)->handle($owner, $project, 'Child', parent: $parent);

        $this->actingAs($owner)->get(route('tasks.show', [$project, $child]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('task.parent.id', $parent->id)
                ->where('can_create_subtask', false)
                ->where('task.subtask_progress.total', 0)
                ->has('task.subtasks', 0));
    }

    public function test_contributor_can_create_a_subtask_from_the_existing_task_route(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $parent = app(CreateTask::class)->handle($owner, $project, 'Parent');

        $this->actingAs($owner)
            ->post(route('tasks.store', $project), [
                'parent_id' => $parent->id,
                'title' => 'Add API tests',
                'type' => TaskType::TASK->value,
                'priority' => TaskPriority::NORMAL->value,
            ])->assertSessionHasNoErrors();

        $child = $project->tasks()->where('parent_id', $parent->id)->sole();
        $this->assertSame('APP-2', $child->reference());
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_subtask_of_subtask_is_rejected_by_existing_validation(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $parent = app(CreateTask::class)->handle($owner, $project, 'Parent');
        $child = app(CreateTask::class)->handle($owner, $project, 'Child', parent: $parent);

        $this->actingAs($owner)->post(route('tasks.store', $project), [
            'parent_id' => $child->id,
            'title' => 'Invalid grandchild',
            'type' => TaskType::TASK->value,
            'priority' => TaskPriority::NORMAL->value,
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame(2, $project->tasks()->count());
    }

    public function test_reader_sees_subtasks_without_creation_privileges(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $parent = app(CreateTask::class)->handle($owner, $project, 'Parent');
        app(CreateTask::class)->handle($owner, $project, 'Child', parent: $parent);
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($reader)->get(route('tasks.show', [$project, $parent]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_create_subtask', false)
                ->has('task.subtasks', 1));

        $this->actingAs($reader)->post(route('tasks.store', $project), [
            'parent_id' => $parent->id,
            'title' => 'Not allowed',
            'type' => TaskType::TASK->value,
            'priority' => TaskPriority::NORMAL->value,
        ])->assertForbidden();
    }

    public function test_other_project_parent_is_rejected(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $other = app(CreateProject::class)->handle($owner, $project->workspace, 'Other', 'WEB');
        $foreignParent = app(CreateTask::class)->handle($owner, $other, 'External parent');

        $this->actingAs($owner)->post(route('tasks.store', $project), [
            'parent_id' => $foreignParent->id,
            'title' => 'Not allowed',
            'type' => TaskType::TASK->value,
            'priority' => TaskPriority::NORMAL->value,
        ])->assertSessionHasErrors('parent_id');
    }

    /** @return array{Project, User} */
    private function projectWithOwner(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        return [app(CreateProject::class)->handle($owner, $workspace, 'TaskFlow', 'APP'), $owner];
    }
}
