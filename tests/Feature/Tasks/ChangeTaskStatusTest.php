<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\ChangeTaskStatus;
use App\Actions\Tasks\CreateTask;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ChangeTaskStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_changes_status_and_records_structured_activity(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Build dashboard');
        $inProgress = $this->statusForCategory($project, TaskStatusCategory::IN_PROGRESS);

        $updated = app(ChangeTaskStatus::class)->handle($owner, $task, $inProgress);

        $this->assertSame($inProgress->id, $updated->status_id);
        $this->assertNull($updated->completed_at);
        $this->assertSame(1, $updated->activities()->count());

        $activity = $updated->activities()->firstOrFail();

        $this->assertSame(TaskActivityType::STATUS_CHANGED, $activity->type);
        $this->assertSame($owner->id, $activity->user_id);
        $this->assertSame('Todo', $activity->data['from']['name']);
        $this->assertSame(TaskStatusCategory::TODO->value, $activity->data['from']['category']);
        $this->assertSame('In Progress', $activity->data['to']['name']);
        $this->assertSame(TaskStatusCategory::IN_PROGRESS->value, $activity->data['to']['category']);
    }

    public function test_moving_to_done_sets_completed_at(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Ship feature');
        $done = $this->statusForCategory($project, TaskStatusCategory::DONE);

        $updated = app(ChangeTaskStatus::class)->handle($owner, $task, $done);

        $this->assertNotNull($updated->completed_at);
    }

    public function test_moving_away_from_done_clears_completed_at(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $done = $this->statusForCategory($project, TaskStatusCategory::DONE);
        $task = app(CreateTask::class)->handle($owner, $project, 'Reopen me', status: $done);
        $todo = $this->statusForCategory($project, TaskStatusCategory::TODO);

        $this->assertNotNull($task->completed_at);

        $updated = app(ChangeTaskStatus::class)->handle($owner, $task, $todo);

        $this->assertNull($updated->completed_at);
    }

    public function test_changing_to_the_same_status_is_a_noop(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Stay here');

        $updated = app(ChangeTaskStatus::class)->handle($owner, $task, $task->status);

        $this->assertSame($task->status_id, $updated->status_id);
        $this->assertSame(0, $updated->activities()->count());
    }

    public function test_it_rejects_a_status_from_another_project(): void
    {
        [$project, $owner] = $this->projectWithOwner('APP');
        $otherProject = app(CreateProject::class)->handle($owner, $project->workspace, 'Other project', 'WEB');
        $task = app(CreateTask::class)->handle($owner, $project, 'Task');
        $foreignStatus = $this->statusForCategory($otherProject, TaskStatusCategory::DONE);

        $this->expectException(InvalidArgumentException::class);

        app(ChangeTaskStatus::class)->handle($owner, $task, $foreignStatus);
    }

    public function test_user_without_contributor_access_cannot_change_status(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $task = app(CreateTask::class)->handle($owner, $project, 'Task');
        $viewer = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->expectException(AuthorizationException::class);

        app(ChangeTaskStatus::class)->handle(
            $viewer,
            $task,
            $this->statusForCategory($project, TaskStatusCategory::IN_PROGRESS),
        );
    }

    private function statusForCategory(Project $project, TaskStatusCategory $category): TaskStatus
    {
        return $project->taskStatuses()
            ->where('category', $category->value)
            ->firstOrFail();
    }

    /**
     * @return array{Project, User}
     */
    private function projectWithOwner(string $key = 'APP'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $project = app(CreateProject::class)->handle($owner, $workspace, 'Task Project', $key);

        return [$project, $owner];
    }
}
