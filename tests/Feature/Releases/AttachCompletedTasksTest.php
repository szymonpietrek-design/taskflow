<?php

namespace Tests\Feature\Releases;

use App\Actions\Releases\AttachCompletedTasks;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatusCategory;
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

class AttachCompletedTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_bulk_action_attaches_only_unassigned_completed_tasks_and_records_activity(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        $eligible = $this->task($project, $owner, $done, now());
        $second = $this->task($project, $owner, $done, now());
        $withoutCompletion = $this->task($project, $owner, $done);
        $notDone = $this->task($project, $owner, $todo, now());
        $other = Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
        $assignedElsewhere = $this->task($project, $owner, $done, now(), $other->id);
        $foreignProject = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $foreign = $this->task($foreignProject, $owner, $this->createTaskStatus($foreignProject, TaskStatusCategory::DONE), now());

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->where('completed_available_count', 2));

        $this->actingAs($owner)->post(route('releases.tasks.attach-completed', [$project, $release]))
            ->assertRedirect(route('releases.show', [$project, $release]))
            ->assertSessionHasNoErrors();

        $this->assertSame($release->id, $eligible->fresh()->release_id);
        $this->assertSame($release->id, $second->fresh()->release_id);
        $this->assertNull($withoutCompletion->fresh()->release_id);
        $this->assertNull($notDone->fresh()->release_id);
        $this->assertSame($other->id, $assignedElsewhere->fresh()->release_id);
        $this->assertNull($foreign->fresh()->release_id);
        $this->assertDatabaseCount('task_activities', 2);
        $this->assertEquals([
            'from' => null,
            'to' => ['id' => $release->id, 'version' => $release->version],
        ], $eligible->activities()->sole()->data);
        $this->assertSame(TaskActivityType::RELEASE_CHANGED, $second->activities()->sole()->type);

        // Repeated bulk actions do not generate duplicate Activity entries.
        $this->actingAs($owner)->post(route('releases.tasks.attach-completed', [$project, $release]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('task_activities', 2);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->where('completed_available_count', 0));

        // Explicit detach is still supported. Only another explicit bulk action may re-attach it.
        $this->actingAs($owner)->delete(route('releases.tasks.destroy', [$project, $release, $eligible]))
            ->assertSessionHasNoErrors();
        $this->assertNull($eligible->fresh()->release_id);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->where('completed_available_count', 1));
    }

    public function test_new_release_option_includes_completed_tasks_when_requested(): void
    {
        [$owner, $project] = $this->fixtures();
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        $completed = $this->task($project, $owner, $done, now());
        $pending = $this->task($project, $owner, $todo);

        $this->actingAs($owner)->get(route('releases.index', $project))
            ->assertInertia(fn (Assert $page) => $page->where('completed_available_count', 1));

        $this->actingAs($owner)->post(route('releases.store', $project), [
            'version' => '2.0.0', 'include_completed_tasks' => '1',
        ])->assertSessionHasNoErrors();
        $release = Release::query()->where('version', '2.0.0')->firstOrFail();
        $this->assertSame($release->id, $completed->fresh()->release_id);
        $this->assertNull($pending->fresh()->release_id);
        $this->assertDatabaseCount('task_activities', 1);
    }

    public function test_unchecked_or_missing_option_does_not_attach_completed_tasks(): void
    {
        [$owner, $project] = $this->fixtures();
        $completed = $this->task($project, $owner, $this->createTaskStatus($project, TaskStatusCategory::DONE), now());

        $this->actingAs($owner)->post(route('releases.store', $project), [
            'version' => '1.0.0', 'include_completed_tasks' => '0',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('releases.store', $project), [
            'version' => '1.1.0',
        ])->assertSessionHasNoErrors();
        $this->assertNull($completed->fresh()->release_id);
        $this->assertDatabaseCount('task_activities', 0);
    }

    public function test_members_without_contributor_rights_cannot_bulk_attach(): void
    {
        [, $project, $release] = $this->fixtures();
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $this->actingAs($reader)->post(route('releases.tasks.attach-completed', [$project, $release]))
            ->assertForbidden();
    }

    public function test_closed_foreign_and_archived_releases_cannot_bulk_attach(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $task = $this->task($project, $owner, $this->createTaskStatus($project, TaskStatusCategory::DONE), now());

        foreach ([ReleaseStatus::RELEASED, ReleaseStatus::CANCELED] as $status) {
            $release->update(['status' => $status]);
            $this->actingAs($owner)->post(route('releases.tasks.attach-completed', [$project, $release]))
                ->assertSessionHasErrors('release');
        }

        $release->update(['status' => ReleaseStatus::PLANNED]);
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $this->actingAs($owner)->post(route('releases.tasks.attach-completed', [$other, $release]))
            ->assertNotFound();

        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->post(route('releases.tasks.attach-completed', [$project, $release]))
            ->assertSessionHasErrors('release');
        $this->assertNull($task->fresh()->release_id);
        $this->assertDatabaseCount('task_activities', 0);
    }

    /** @return array{User, Project, Release} */
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

        return [$owner, $project, Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id])];
    }

    private function createTaskStatus(Project $project, TaskStatusCategory $category): TaskStatus
    {
        return TaskStatus::factory()->create(['project_id' => $project->id, 'category' => $category]);
    }

    private function task(Project $project, User $owner, TaskStatus $status, ?\DateTimeInterface $completedAt = null, ?int $releaseId = null): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status_id' => $status->id,
            'completed_at' => $completedAt,
            'release_id' => $releaseId,
        ]);
    }
}
