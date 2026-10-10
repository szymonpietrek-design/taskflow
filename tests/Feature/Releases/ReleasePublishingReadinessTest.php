<?php

namespace Tests\Feature\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
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

class ReleasePublishingReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_todo_in_progress_canceled_and_done_without_completion_date_block_publication(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        $working = $this->createTaskStatus($project, TaskStatusCategory::IN_PROGRESS);
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $canceled = $this->createTaskStatus($project, TaskStatusCategory::CANCELED);

        $this->createTask($owner, $project, $release, $todo, 1);
        $this->createTask($owner, $project, $release, $working, 2);
        $this->createTask($owner, $project, $release, $canceled, 3);
        $this->createTask($owner, $project, $release, $done, 4); // Done without completed_at.
        $this->createTask($owner, $project, $release, $done, 5, true);
        $this->createTask($owner, $project, null, $todo, 6); // Unassigned: ignored.

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('release_progress.total', 5)
                ->where('release_progress.completed', 1)
                ->has('release_blockers', 4)
                ->where('release_blockers.0.reference', $project->key.'-1')
                ->where('release_blockers.3.missing_completion_date', true));

        $this->actingAs($owner)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasErrors(['status']);

        $this->assertSame(ReleaseStatus::PLANNED, $release->fresh()->status);
        $this->assertNull($release->fresh()->released_at);
        $this->assertSame(5, $release->tasks()->count());
    }

    public function test_completed_tasks_allow_publication_and_get_release_timestamp(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $this->createTask($owner, $project, $release, $done, 1, true);
        $this->createTask($owner, $project, $release, $done, 2, true);
        $this->freezeTime();

        $this->actingAs($owner)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasNoErrors()->assertRedirect(route('releases.show', [$project, $release]));

        $this->assertSame(ReleaseStatus::RELEASED, $release->fresh()->status);
        $this->assertSame(now()->toDateTimeString(), $release->fresh()->released_at?->toDateTimeString());
    }

    public function test_planned_release_without_tasks_can_be_published(): void
    {
        [$owner, $project, $release] = $this->fixtures();

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('release_progress.total', 0)
                ->has('release_blockers', 0));

        $this->actingAs($owner)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(ReleaseStatus::RELEASED, $release->fresh()->status);
    }

    public function test_publication_checks_current_status_in_database_even_if_page_was_previously_ready(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $working = $this->createTaskStatus($project, TaskStatusCategory::IN_PROGRESS);
        $task = $this->createTask($owner, $project, $release, $done, 1, true);

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->has('release_blockers', 0));

        $task->update(['status_id' => $working->id, 'completed_at' => null]);
        $this->actingAs($owner)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasErrors(['status']);
        $this->assertSame(ReleaseStatus::PLANNED, $release->fresh()->status);
    }

    public function test_removing_an_unfinished_task_allows_publication(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        $task = $this->createTask($owner, $project, $release, $todo, 1);

        $this->actingAs($owner)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasErrors(['status']);

        $task->update(['release_id' => null]);
        $this->actingAs($owner)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasNoErrors();
        $this->assertSame(ReleaseStatus::RELEASED, $release->fresh()->status);
        $this->assertNull($task->fresh()->release_id);
    }

    public function test_blocker_preview_is_bounded_even_if_release_has_many_unfinished_tasks(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        for ($number = 1; $number <= 7; $number++) {
            $this->createTask($owner, $project, $release, $todo, $number);
        }
        $otherRelease = Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
        $this->createTask($owner, $project, $otherRelease, $todo, 8);

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('release_progress.total', 7)
                ->has('release_blockers', 5));
    }

    public function test_reader_cannot_publish_by_bypassing_disabled_button(): void
    {
        [, $project, $release] = $this->fixtures();
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($reader)->patch($this->statusUrl($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertForbidden();
        $this->assertSame(ReleaseStatus::PLANNED, $release->fresh()->status);
    }

    /** @return array{User, Project, Release} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $release = Release::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status' => ReleaseStatus::PLANNED,
        ]);

        return [$owner, $project, $release];
    }

    private function createTaskStatus(Project $project, TaskStatusCategory $category): TaskStatus
    {
        return TaskStatus::factory()->create([
            'project_id' => $project->id,
            'category' => $category->value,
        ]);
    }

    private function createTask(
        User $owner,
        Project $project,
        ?Release $release,
        TaskStatus $status,
        int $number,
        bool $completed = false,
    ): Task {
        return Task::factory()->create([
            'project_id' => $project->id,
            'release_id' => $release?->id,
            'created_by' => $owner->id,
            'status_id' => $status->id,
            'number' => $number,
            'completed_at' => $completed ? now() : null,
        ]);
    }

    private function statusUrl(Project $project, Release $release): string
    {
        return route('releases.status.update', [$project, $release]);
    }
}
