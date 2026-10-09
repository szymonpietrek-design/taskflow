<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\ManageTaskStatuses;
use App\Enums\ProjectVisibility;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageTaskStatusesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_view_default_workflow_and_manage_link_is_available(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->actingAs($owner)->get(route('projects.statuses.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Statuses')
                ->where('project.id', $project->id)
                ->where('can_manage', true)
                ->has('statuses', 4)
                ->where('statuses.0.name', 'Todo')
                ->where('statuses.1.name', 'In Progress')
                ->where('statuses.2.name', 'Done')
                ->where('statuses.3.name', 'Canceled'),
            );
    }

    public function test_owner_can_add_status_and_duplicate_name_is_rejected(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        $this->actingAs($owner)->post(route('projects.statuses.store', $project), [
            'name' => '  Code Review  ',
            'category' => TaskStatusCategory::IN_PROGRESS->value,
        ])->assertSessionHasNoErrors()->assertRedirect(route('projects.statuses.index', $project));

        $status = $project->taskStatuses()->where('name', 'Code Review')->sole();
        $this->assertSame('code-review', $status->slug);
        $this->assertSame(TaskStatusCategory::IN_PROGRESS, $status->category);
        $this->assertSame(5000, $status->position);

        $this->actingAs($owner)->post(route('projects.statuses.store', $project), [
            'name' => 'Code Review',
            'category' => 2,
        ])->assertSessionHasErrors('name');
    }

    public function test_similar_names_receive_different_slugs(): void
    {
        [$project, $owner] = $this->projectWithOwner();

        foreach (['Review & Testing', 'Review Testing'] as $name) {
            $this->actingAs($owner)->post(route('projects.statuses.store', $project), [
                'name' => $name,
                'category' => TaskStatusCategory::IN_PROGRESS->value,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['review-testing', 'review-testing-2'],
            $project->taskStatuses()->whereIn('name', ['Review & Testing', 'Review Testing'])
                ->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_owner_can_rename_and_recategorize_unused_status_without_changing_slug(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $status = $project->taskStatuses()->where('slug', 'in-progress')->sole();

        $this->actingAs($owner)->patch(route('projects.statuses.update', [$project, $status]), [
            'name' => 'In Development',
            'category' => TaskStatusCategory::TODO->value,
        ])->assertSessionHasNoErrors();

        $status->refresh();
        $this->assertSame('In Development', $status->name);
        $this->assertSame('in-progress', $status->slug);
        $this->assertSame(TaskStatusCategory::TODO, $status->category);
    }

    public function test_category_of_status_with_tasks_cannot_change_but_renaming_is_safe(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Discuss this task');
        $status = $task->status;

        $this->actingAs($owner)->patch(route('projects.statuses.update', [$project, $status]), [
            'name' => 'Backlog',
            'category' => TaskStatusCategory::IN_PROGRESS->value,
        ])->assertSessionHasErrors('status');
        $this->assertSame(TaskStatusCategory::TODO, $status->fresh()->category);

        $this->actingAs($owner)->patch(route('projects.statuses.update', [$project, $status]), [
            'name' => 'Backlog',
            'category' => TaskStatusCategory::TODO->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame($status->id, $task->fresh()->status_id);
        $this->assertSame(0, $task->activities()->count());
    }

    public function test_moving_status_changes_order_and_does_not_touch_tasks(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $todo = $project->taskStatuses()->where('slug', 'todo')->firstOrFail();
        $progress = $project->taskStatuses()->where('slug', 'in-progress')->firstOrFail();
        $task = app(CreateTask::class)->handle($owner, $project, 'Keep current status');

        $this->actingAs($owner)->patch(route('projects.statuses.move', [$project, $progress]), [
            'direction' => 'up',
        ])->assertSessionHasNoErrors();

        $names = $project->taskStatuses()->orderBy('position')->orderBy('id')->pluck('name')->all();
        $this->assertSame(['In Progress', 'Todo', 'Done', 'Canceled'], $names);
        $this->assertSame($todo->id, $task->fresh()->status_id);
        $this->assertSame(0, $task->activities()->count());

        // A boundary move is a no-op and invalid directions are rejected.
        $this->actingAs($owner)->patch(route('projects.statuses.move', [$project, $progress]), [
            'direction' => 'up',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('projects.statuses.move', [$project, $progress]), [
            'direction' => 'sideways',
        ])->assertSessionHasErrors('direction');
    }

    public function test_delete_is_restricted_to_unused_statuses_and_last_todo_is_protected(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $todo = $project->taskStatuses()->where('slug', 'todo')->sole();
        $done = $project->taskStatuses()->where('slug', 'done')->sole();

        $this->actingAs($owner)->delete(route('projects.statuses.destroy', [$project, $todo]))
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('task_statuses', ['id' => $todo->id]);

        $task = app(CreateTask::class)->handle($owner, $project, 'Assigned');
        $this->actingAs($owner)->delete(route('projects.statuses.destroy', [$project, $todo]))
            ->assertSessionHasErrors('status');
        $this->assertSame($todo->id, $task->fresh()->status_id);

        $this->actingAs($owner)->delete(route('projects.statuses.destroy', [$project, $done]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('task_statuses', ['id' => $done->id]);
    }

    public function test_last_todo_cannot_be_recategorized_even_when_unused(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $todo = $project->taskStatuses()->where('slug', 'todo')->firstOrFail();

        $this->actingAs($owner)->patch(route('projects.statuses.update', [$project, $todo]), [
            'name' => 'Waiting', 'category' => TaskStatusCategory::IN_PROGRESS->value,
        ])->assertSessionHasErrors('status');
        $this->assertSame(TaskStatusCategory::TODO, $todo->fresh()->category);
    }

    public function test_viewer_can_read_visible_workflow_but_cannot_modify_it(): void
    {
        [$project] = $this->projectWithOwner();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)->get(route('projects.statuses.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_manage', false));
        $this->actingAs($viewer)->post(route('projects.statuses.store', $project), [
            'name' => 'Forbidden', 'category' => 1,
        ])->assertForbidden();
        $this->assertDatabaseMissing('task_statuses', ['name' => 'Forbidden', 'project_id' => $project->id]);

        $project->forceFill(['visibility' => ProjectVisibility::PRIVATE])->save();
        $this->actingAs($viewer)->get(route('projects.statuses.index', $project))->assertForbidden();
    }

    public function test_foreign_status_is_not_editable_movable_or_deletable(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$foreign] = $this->projectWithOwner('OTHER');
        $status = $foreign->taskStatuses()->firstOrFail();

        $this->actingAs($owner)->patch(route('projects.statuses.update', [$project, $status]), [
            'name' => 'Hacked', 'category' => 1,
        ])->assertNotFound();
        $this->actingAs($owner)->patch(route('projects.statuses.move', [$project, $status]), [
            'direction' => 'up',
        ])->assertNotFound();
        $this->actingAs($owner)->delete(route('projects.statuses.destroy', [$project, $status]))
            ->assertNotFound();
        $this->assertNotSame('Hacked', $status->fresh()->name);
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
