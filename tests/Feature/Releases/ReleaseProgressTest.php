<?php

namespace Tests\Feature\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskStatusCategory;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_progress_counts_all_release_tasks_and_uses_done_status_with_completion_date(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        $inProgress = $this->createTaskStatus($project, TaskStatusCategory::IN_PROGRESS);
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $canceled = $this->createTaskStatus($project, TaskStatusCategory::CANCELED);

        for ($number = 1; $number <= 13; $number++) {
            $this->task($project, $release, $owner, $done, $number, true);
        }
        $this->task($project, $release, $owner, $done, 14); // Done without timestamp is not completed.
        $this->task($project, $release, $owner, $todo, 15);
        $this->task($project, $release, $owner, $inProgress, 16);
        $this->task($project, $release, $owner, $inProgress, 17);
        $this->task($project, $release, $owner, $canceled, 18);
        $this->task($project, null, $owner, $done, 19, true); // Unassigned task is ignored.
        $another = Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
        $this->task($project, $another, $owner, $done, 20, true); // Another release is ignored.

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('task_count', 18)
                ->has('tasks.data', 15)
                ->where('release_progress', [
                    'total' => 18,
                    'completed' => 13,
                    'todo' => 1,
                    'in_progress' => 2,
                    'done' => 14,
                    'canceled' => 1,
                    'percentage' => 72,
                ]));
    }

    public function test_empty_release_has_zero_progress_visible_to_workspace_member(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($member)->get(route('releases.show', [$project, $release]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('can_manage_tasks', false)
                ->where('release_progress', [
                    'total' => 0,
                    'completed' => 0,
                    'todo' => 0,
                    'in_progress' => 0,
                    'done' => 0,
                    'canceled' => 0,
                    'percentage' => 0,
                ]));
    }

    public function test_progress_reflects_status_changes_and_removed_tasks(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $todo = $this->createTaskStatus($project, TaskStatusCategory::TODO);
        $done = $this->createTaskStatus($project, TaskStatusCategory::DONE);
        $task = $this->task($project, $release, $owner, $todo, 1);

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('release_progress.percentage', 0));

        $task->update(['status_id' => $done->id, 'completed_at' => now()]);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('release_progress.completed', 1)
                ->where('release_progress.percentage', 100));

        $task->update(['release_id' => null]);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('release_progress.total', 0)
                ->where('release_progress.percentage', 0));
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

    private function task(
        Project $project,
        ?Release $release,
        User $owner,
        TaskStatus $status,
        int $number,
        bool $completed = false,
    ): Task {
        return Task::factory()->create([
            'project_id' => $project->id,
            'release_id' => $release?->id,
            'status_id' => $status->id,
            'created_by' => $owner->id,
            'number' => $number,
            'completed_at' => $completed ? now() : null,
        ]);
    }
}
