<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\ProjectVisibility;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_search_matches_title_description_and_project_reference_without_leaking_other_projects(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$other, $otherOwner] = $this->projectWithOwner('OTHER');
        app(CreateTask::class)->handle($owner, $project, 'Fix login');
        $second = app(CreateTask::class)->handle($owner, $project, 'Update documentation', description: 'OAuth refresh flow');
        app(CreateTask::class)->handle($otherOwner, $other, 'OAuth in another project');

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'q' => 'OAuth']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)->where('tasks.0.id', $second->id)
            ->where('filters.q', 'OAuth')->where('pagination.total', 1));

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'q' => 'APP-2']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)->where('tasks.0.id', $second->id));

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'q' => '%']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 0)->where('pagination.total', 0));
    }

    public function test_filtered_count_preserves_the_total_number_of_tasks_in_this_project(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$other, $otherOwner] = $this->projectWithOwner('OTHER');
        app(CreateTask::class)->handle($owner, $project, 'Implement API');
        app(CreateTask::class)->handle($owner, $project, 'Write docs');
        app(CreateTask::class)->handle($otherOwner, $other, 'External task');

        $this->actingAs($owner)->get(route('tasks.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pagination.total', 2)
                ->where('pagination.unfiltered_total', 2));

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'q' => 'missing']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 0)
                ->where('pagination.total', 0)
                ->where('pagination.unfiltered_total', 2));

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'q' => 'API']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 1)
                ->where('pagination.total', 1)
                ->where('pagination.unfiltered_total', 2));

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'sort' => 'oldest']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('pagination.total', 2)
                ->where('pagination.unfiltered_total', 2));
    }

    public function test_status_priority_and_assignee_filters_can_be_combined(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        $progress = $project->taskStatuses()->where('category', TaskStatusCategory::IN_PROGRESS->value)->firstOrFail();
        $target = app(CreateTask::class)->handle($owner, $project, 'Review API', priority: TaskPriority::HIGH, status: $progress, assignee: $owner);
        app(CreateTask::class)->handle($owner, $project, 'Review API unassigned', priority: TaskPriority::HIGH, status: $progress);
        app(CreateTask::class)->handle($owner, $project, 'Review login', priority: TaskPriority::NORMAL, assignee: $owner);

        $this->actingAs($owner)->get(route('tasks.index', [
            'project' => $project, 'q' => 'Review', 'status_id' => $progress->id,
            'priority' => TaskPriority::HIGH->value, 'assignee_id' => $owner->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)->where('tasks.0.id', $target->id)
            ->where('filters.priority', (string) TaskPriority::HIGH->value)
            ->where('filters.assignee_id', (string) $owner->id));

        $this->actingAs($owner)->get(route('tasks.index', [
            'project' => $project, 'assignee_id' => 'unassigned',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)->where('pagination.total', 1)
            ->where('filters.assignee_id', 'unassigned'));
    }

    public function test_paginated_results_preserve_sort_and_parent_options_are_not_limited_to_one_page(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        for ($number = 1; $number <= 17; $number++) {
            app(CreateTask::class)->handle($owner, $project, 'Task '.$number);
        }

        $this->actingAs($owner)->get(route('tasks.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 15)->where('tasks.0.reference', 'APP-17')
            ->where('pagination.total', 17)->where('pagination.last_page', 2)
            ->has('parent_tasks', 17));

        $this->actingAs($owner)->get(route('tasks.index', [
            'project' => $project, 'sort' => 'oldest', 'page' => 2,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 2)->where('tasks.0.reference', 'APP-16')
            ->where('pagination.current_page', 2)->where('pagination.total', 17)
            ->where('filters.sort', 'oldest')->has('parent_tasks', 17)
            ->where('pagination.previous_page_url', fn (?string $url): bool => $url !== null && str_contains($url, 'sort=oldest')));
    }

    public function test_sorting_by_priority_and_due_date_is_predictable(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        app(CreateTask::class)->handle($owner, $project, 'Later', priority: TaskPriority::LOW, dueDate: new DateTimeImmutable('2026-11-15'));
        app(CreateTask::class)->handle($owner, $project, 'Urgent', priority: TaskPriority::URGENT, dueDate: new DateTimeImmutable('2026-11-03'));
        app(CreateTask::class)->handle($owner, $project, 'Undated', priority: TaskPriority::NORMAL);

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'sort' => 'priority']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.title', 'Urgent')->where('tasks.2.title', 'Later'));

        $this->actingAs($owner)->get(route('tasks.index', ['project' => $project, 'sort' => 'due_date']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.title', 'Urgent')->where('tasks.1.title', 'Later')
            ->where('tasks.2.title', 'Undated'));
    }

    public function test_task_list_includes_aggregated_worklogs_for_each_task_without_leaking_other_projects(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$otherProject, $otherOwner] = $this->projectWithOwner('OTHER');
        $first = app(CreateTask::class)->handle($owner, $project, 'With time');
        $empty = app(CreateTask::class)->handle($owner, $project, 'Without time');
        $foreign = app(CreateTask::class)->handle($otherOwner, $otherProject, 'Foreign time');

        $first->worklogs()->create([
            'user_id' => $owner->id, 'work_date' => '2026-05-10',
            'duration_minutes' => 90, 'description' => null, 'is_creative' => false,
        ]);
        $first->worklogs()->create([
            'user_id' => $owner->id, 'work_date' => '2026-05-11',
            'duration_minutes' => 45, 'description' => 'Review', 'is_creative' => true,
        ]);
        $foreign->worklogs()->create([
            'user_id' => $otherOwner->id, 'work_date' => '2026-05-11',
            'duration_minutes' => 480, 'description' => null, 'is_creative' => false,
        ]);

        $this->actingAs($owner)->get(route('tasks.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tasks/Index')
            ->has('tasks', 2)
            ->where('tasks.0.id', $empty->id)
            ->where('tasks.0.worklog_total_minutes', 0)
            ->where('tasks.0.worklog_entries_count', 0)
            ->where('tasks.1.id', $first->id)
            ->where('tasks.1.worklog_total_minutes', 135)
            ->where('tasks.1.worklog_entries_count', 2)
            );

        $this->actingAs($owner)->get(route('tasks.index', [
            'project' => $project, 'q' => 'With time',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.worklog_total_minutes', 135)
            ->where('tasks.0.worklog_entries_count', 2));
    }

    public function test_invalid_filters_and_foreign_status_are_rejected(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        [$other] = $this->projectWithOwner('OTHER');
        $foreignStatus = $other->taskStatuses()->firstOrFail();

        $this->actingAs($owner)->get(route('tasks.index', [
            'project' => $project, 'priority' => 50, 'sort' => 'raw_sql', 'status_id' => $foreignStatus->id,
        ]))->assertSessionHasErrors(['priority', 'sort', 'status_id']);
    }

    public function test_unassigned_workspace_member_cannot_read_tasks_regardless_of_visibility(): void
    {
        [$project, $owner] = $this->projectWithOwner();
        app(CreateTask::class)->handle($owner, $project, 'Assigned', assignee: $owner);
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)->get(route('tasks.index', ['project' => $project, 'assignee_id' => $owner->id]))->assertForbidden();

        $project->forceFill(['visibility' => ProjectVisibility::PRIVATE])->save();
        $this->actingAs($viewer)->get(route('tasks.index', $project))->assertForbidden();
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
