<?php

namespace Tests\Feature\Releases;

use App\Actions\Releases\SetTaskRelease;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_assigns_and_removes_task_with_audit_snapshots(): void
    {
        [$owner, $project, $release, $task] = $this->fixtures();

        $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $release]), [
            'task_id' => $task->id,
        ])->assertRedirect(route('releases.show', [$project, $release]));
        $this->assertSame($release->id, $task->fresh()->release_id);
        $this->assertSame(TaskActivityType::RELEASE_CHANGED, $task->activities()->first()->type);
        $this->assertEquals([
            'from' => null,
            'to' => ['id' => $release->id, 'version' => $release->version],
        ], $task->activities()->first()->data);

        $this->actingAs($owner)->delete(route('releases.tasks.destroy', [$project, $release, $task]))
            ->assertRedirect(route('releases.show', [$project, $release]));
        $this->assertNull($task->fresh()->release_id);
        $this->assertSame(2, $task->activities()->count());
        $this->assertEquals([
            'from' => ['id' => $release->id, 'version' => $release->version],
            'to' => null,
        ], $task->activities()->orderByDesc('id')->first()->data);
    }

    public function test_inertia_lists_assigned_tasks_and_searches_available_tasks(): void
    {
        [$owner, $project, $release, $task] = $this->fixtures();
        $task->update(['title' => 'Invoice notifications', 'release_id' => $release->id]);
        $unassigned = $this->task($project, $owner);
        $unassigned->update(['title' => 'Implement profile']);

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('task_count', 1)
                ->where('tasks.data.0.id', $task->id)
                ->where('available_count', 1)
                ->where('available_tasks.0.id', $unassigned->id)
                ->where('can_manage_tasks', true));

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]).'?q=profile')
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('available_count', 1)->where('available_tasks.0.id', $unassigned->id));

        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page->component('tasks/Show')
                ->where('task.release.id', $release->id)
                ->where('task.release.version', $release->version));
    }

    public function test_second_assignment_is_noop_without_extra_activity(): void
    {
        [$owner, , $release, $task] = $this->fixtures();
        $action = app(SetTaskRelease::class);
        $action->handle($owner, $task, $release);
        $action->handle($owner, $task->fresh(), $release);
        $this->assertDatabaseCount('task_activities', 1);
    }

    public function test_moving_between_releases_requires_detach_first(): void
    {
        [$owner, $project, $release, $task] = $this->fixtures();
        $another = $this->release($project, $owner);
        $task->update(['release_id' => $release->id]);

        $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $another]), [
            'task_id' => $task->id,
        ])->assertSessionHasErrors(['task_id']);
        $this->assertSame($release->id, $task->fresh()->release_id);
        $this->assertDatabaseCount('task_activities', 0);
    }

    public function test_cross_project_tasks_and_releases_are_rejected(): void
    {
        [$owner, $project, $release, $task] = $this->fixtures();
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $foreignTask = $this->task($other, $owner);
        $foreignRelease = $this->release($other, $owner);

        $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $release]), [
            'task_id' => $foreignTask->id,
        ])->assertSessionHasErrors(['task_id']);
        $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $foreignRelease]), [
            'task_id' => $task->id,
        ])->assertNotFound();
        $this->actingAs($owner)->delete(route('releases.tasks.destroy', [$project, $release, $foreignTask]))
            ->assertNotFound();
        $this->assertNull($task->fresh()->release_id);
    }

    public function test_reader_can_view_release_but_cannot_assign_task(): void
    {
        [, $project, $release, $task] = $this->fixtures();
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $this->actingAs($reader)->get(route('releases.show', [$project, $release]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('releases/Show')
            ->where('can_manage_tasks', false)->where('available_count', 0));
        $this->actingAs($reader)->post(route('releases.tasks.store', [$project, $release]), [
            'task_id' => $task->id,
        ])->assertForbidden();
        $this->assertNull($task->fresh()->release_id);
    }

    public function test_closed_release_cannot_add_or_remove_tasks(): void
    {
        [$owner, $project, $release, $task] = $this->fixtures();
        foreach ([ReleaseStatus::RELEASED, ReleaseStatus::CANCELED] as $closed) {
            $release->update(['status' => $closed]);
            $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $release]), [
                'task_id' => $task->id,
            ])->assertSessionHasErrors(['task_id']);
        }
        $release->update(['status' => ReleaseStatus::DRAFT]);
        $task->update(['release_id' => $release->id]);
        $release->update(['status' => ReleaseStatus::RELEASED]);
        $this->actingAs($owner)->delete(route('releases.tasks.destroy', [$project, $release, $task]))
            ->assertSessionHasErrors(['release']);
        $this->assertSame($release->id, $task->fresh()->release_id);
    }

    public function test_archived_and_standard_projects_cannot_change_assignments(): void
    {
        [$owner, $project, $release, $task] = $this->fixtures();
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $release]), [
            'task_id' => $task->id,
        ])->assertSessionHasErrors(['task_id']);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('can_manage_tasks', false));

        $project->update(['archived_at' => null, 'type' => ProjectType::STANDARD]);
        $this->actingAs($owner)->post(route('releases.tasks.store', [$project, $release]), [
            'task_id' => $task->id,
        ])->assertNotFound();
    }

    /** @return array{User, Project, Release, Task} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id, 'created_by' => $owner->id, 'type' => ProjectType::APPLICATION,
        ]);

        return [$owner, $project, $this->release($project, $owner), $this->task($project, $owner)];
    }

    private function release(Project $project, User $owner): Release
    {
        return Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
    }

    private function task(Project $project, User $owner): Task
    {
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);

        return Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status_id' => $status->id,
        ]);
    }
}
