<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\ProjectVisibility;
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

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_task_index_lists_only_the_selected_project_tasks(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject, $otherOwner] = $this->projectWithOwner('OTHER');
        $task = app(CreateTask::class)->handle($owner, $project, 'First task');
        app(CreateTask::class)->handle($otherOwner, $otherProject, 'Private to other project');

        $this->actingAs($owner)
            ->get(route('tasks.index', $project))
            ->assertOk()
            ->assertSessionHas('current_workspace_id', $project->workspace_id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Index')
                ->where('project.key', 'APP')
                ->has('tasks', 1)
                ->where('tasks.0.reference', 'APP-1')
                ->where('tasks.0.id', $task->id)
                ->where('can_create', true),
            );
    }

    public function test_create_task_form_uses_existing_action_and_redirects_to_task(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $status = $project->taskStatuses()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('tasks.store', $project), [
                'title' => 'Improve login',
                'description' => 'Support passkeys',
                'type' => TaskType::FEATURE->value,
                'priority' => TaskPriority::HIGH->value,
                'status_id' => $status->id,
                'assignee_id' => $owner->id,
                'due_date' => '2026-11-02',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', [$project, $project->tasks()->sole()]));

        $task = $project->tasks()->sole();
        $this->assertSame('APP-1', $task->reference());
        $this->assertSame(TaskType::FEATURE, $task->type);
        $this->assertSame(TaskPriority::HIGH, $task->priority);
        $this->assertSame($owner->id, $task->assignee_id);
        $this->assertSame('2026-11-02', $task->due_date->toDateString());
    }

    public function test_invalid_fields_and_foreign_project_status_are_rejected(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$foreign] = $this->projectWithOwner('OTHER');
        $foreignStatus = $foreign->taskStatuses()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('tasks.store', $project), [
                'title' => '',
                'type' => 88,
                'priority' => 88,
                'status_id' => $foreignStatus->id,
            ])
            ->assertSessionHasErrors(['title', 'type', 'priority', 'status_id']);

        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_unassigned_workspace_member_cannot_view_or_create_task(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)
            ->get(route('tasks.index', $project))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('tasks.store', $project), [
                'title' => 'Blocked',
                'type' => 1,
                'priority' => 2,
            ])
            ->assertForbidden();
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

        $this->actingAs($owner)
            ->post(route('tasks.store', $project), [
                'title' => 'Task with invalid assignee',
                'type' => 1,
                'priority' => 2,
                'assignee_id' => $viewer->id,
            ])
            ->assertSessionHasErrors('assignee_id');
    }

    public function test_task_detail_is_scoped_to_project_and_checks_permissions(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject] = $this->projectWithOwner('OTHER');
        $task = app(CreateTask::class)->handle($owner, $project, 'Visible task');

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('task.reference', 'APP-1')
                ->where('task.title', 'Visible task'),
            );

        $this->actingAs($owner)
            ->get(route('tasks.show', [$otherProject, $task]))
            ->assertNotFound();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->get(route('tasks.show', [$project, $task]))
            ->assertForbidden();
    }

    public function test_status_change_uses_activity_action_and_completed_at(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Finish report');
        $done = $project->taskStatuses()->where('category', TaskStatusCategory::DONE->value)->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('tasks.status.update', [$project, $task]), ['status_id' => $done->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', [$project, $task]));

        $this->assertSame($done->id, $task->fresh()->status_id);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(1, $task->activities()->count());
    }

    public function test_status_change_rejects_status_from_another_project(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject] = $this->projectWithOwner('OTHER');
        $task = app(CreateTask::class)->handle($owner, $project, 'My task');
        $foreignStatus = $otherProject->taskStatuses()->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('tasks.status.update', [$project, $task]), [
                'status_id' => $foreignStatus->id,
            ])
            ->assertSessionHasErrors('status_id');

        $this->assertSame(0, $task->activities()->count());
    }

    public function test_guest_must_authenticate_before_accessing_tasks(): void
    {
        [$project] = $this->projectWithOwner();

        $this->get(route('tasks.index', $project))->assertRedirect(route('login'));
    }

    public function test_private_project_tasks_are_inaccessible_to_unassigned_members(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $project->forceFill(['visibility' => ProjectVisibility::PRIVATE])->save();
        $task = app(CreateTask::class)->handle($owner, $project, 'Restricted task');
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)->get(route('tasks.index', $project))->assertForbidden();
        $this->actingAs($viewer)->get(route('tasks.show', [$project, $task]))->assertForbidden();
    }

    /** @return array{Project, User} */
    private function projectWithOwner(string $key = 'APP'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        return [app(CreateProject::class)->handle($owner, $workspace, 'Project '.$key, $key), $owner];
    }
}
