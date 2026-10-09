<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTaskDetails;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class UpdateTaskDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_can_update_task_details_and_record_a_separate_activity_per_changed_field(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$project, $task]), [
                'title' => '  Updated title  ',
                'description' => 'A new description',
                'priority' => TaskPriority::URGENT->value,
                'assignee_id' => $owner->id,
                'due_date' => '2026-11-20',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', [$project, $task]));

        $task->refresh();
        $this->assertSame('Updated title', $task->title);
        $this->assertSame('A new description', $task->description);
        $this->assertSame(TaskPriority::URGENT, $task->priority);
        $this->assertSame($owner->id, $task->assignee_id);
        $this->assertSame('2026-11-20', $task->due_date->toDateString());
        $this->assertCount(5, $task->activities);
        $this->assertEqualsCanonicalizing([
            TaskActivityType::TITLE_CHANGED,
            TaskActivityType::DESCRIPTION_CHANGED,
            TaskActivityType::PRIORITY_CHANGED,
            TaskActivityType::ASSIGNEE_CHANGED,
            TaskActivityType::DUE_DATE_CHANGED,
        ], $task->activities->pluck('type')->all());
        $this->assertTrue($task->activities->every(fn ($activity) => $activity->user_id === $owner->id));

        $priorityActivity = $task->activities->firstWhere('type', TaskActivityType::PRIORITY_CHANGED);
        // MySQL JSON columns may reorder object keys. Assert each field strictly instead.
        $this->assertCount(2, $priorityActivity->data['from']);
        $this->assertSame(TaskPriority::NORMAL->value, $priorityActivity->data['from']['value']);
        $this->assertSame('NORMAL', $priorityActivity->data['from']['name']);
        $this->assertCount(2, $priorityActivity->data['to']);
        $this->assertSame(TaskPriority::URGENT->value, $priorityActivity->data['to']['value']);
        $this->assertSame('URGENT', $priorityActivity->data['to']['name']);
        $this->assertSame('Initial task', $task->activities->firstWhere('type', TaskActivityType::TITLE_CHANGED)->data['from']);
        $this->assertSame('Updated title', $task->activities->firstWhere('type', TaskActivityType::TITLE_CHANGED)->data['to']);
        $this->assertSame(['id' => $owner->id, 'name' => $owner->name], $task->activities->firstWhere('type', TaskActivityType::ASSIGNEE_CHANGED)->data['to']);
    }

    public function test_unchanged_details_do_not_create_activity(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$project, $task]), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $task->activities()->count());
    }

    public function test_can_clear_description_assignee_and_due_date(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $task->forceFill(['description' => 'Original', 'assignee_id' => $owner->id, 'due_date' => '2026-11-20'])->save();

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$project, $task]), $this->payload([
                'description' => '',
                'assignee_id' => '',
                'due_date' => '',
            ]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertNull($task->description);
        $this->assertNull($task->assignee_id);
        $this->assertNull($task->due_date);
        $this->assertSame(3, $task->activities()->count());
        $this->assertNull($task->activities()->where('type', TaskActivityType::ASSIGNEE_CHANGED)->firstOrFail()->data['to']);
        $this->assertSame('2026-11-20', $task->activities()->where('type', TaskActivityType::DUE_DATE_CHANGED)->firstOrFail()->data['from']);
    }

    public function test_validation_rejects_invalid_title_priority_date_and_assignee(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$project, $task]), $this->payload([
                'title' => '',
                'priority' => 88,
                'due_date' => '20/11/2026',
                'assignee_id' => 99999,
            ]))
            ->assertSessionHasErrors(['title', 'priority', 'due_date', 'assignee_id']);

        $this->assertSame('Initial task', $task->fresh()->title);
        $this->assertSame(0, $task->activities()->count());
    }

    public function test_cannot_assign_a_user_who_is_not_a_project_contributor(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$project, $task]), $this->payload(['assignee_id' => $viewer->id]))
            ->assertSessionHasErrors('assignee_id');

        $this->assertSame(0, $task->activities()->count());
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_update_action_checks_assignee_and_does_not_persist_partial_changes(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $outsider = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(UpdateTaskDetails::class)->handle(
                $owner, $task, 'Should not persist', null, TaskPriority::HIGH, $outsider, null,
            );
        } finally {
            $this->assertSame('Initial task', $task->fresh()->title);
            $this->assertSame(0, $task->activities()->count());
        }
    }

    public function test_read_only_workspace_member_cannot_edit_task(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)
            ->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('can_update', false)
                ->has('assignees', 0));

        $this->actingAs($viewer)
            ->patch(route('tasks.update', [$project, $task]), $this->payload(['title' => 'Unauthorized']))
            ->assertForbidden();

        $this->assertSame('Initial task', $task->fresh()->title);
    }

    public function test_foreign_project_task_returns_not_found_and_outsider_is_forbidden(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        [$foreign] = $this->taskWithOwner('OTHER');

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$foreign, $task]), $this->payload())
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', [$project, $task]), $this->payload())
            ->assertForbidden();
    }

    public function test_task_show_provides_editable_assignees_to_contributors(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('can_update', true)
                ->where('assignees.0.id', $owner->id));
    }

    public function test_details_update_leaves_status_and_completed_at_untouched(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $beforeStatus = $task->status_id;

        $this->actingAs($owner)
            ->patch(route('tasks.update', [$project, $task]), $this->payload(['title' => 'Another title']))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame($beforeStatus, $task->status_id);
        $this->assertNull($task->completed_at);
        $this->assertSame(1, $task->activities()->count());
    }

    /** @return array{Project, User, Task} */
    private function taskWithOwner(string $key = 'APP'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $project = app(CreateProject::class)->handle($owner, $workspace, 'Project '.$key, $key);
        $task = app(CreateTask::class)->handle($owner, $project, 'Initial task');

        return [$project, $owner, $task];
    }

    /** @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_replace([
            'title' => 'Initial task',
            'description' => '',
            'priority' => TaskPriority::NORMAL->value,
            'assignee_id' => '',
            'due_date' => '',
        ], $override);
    }
}
