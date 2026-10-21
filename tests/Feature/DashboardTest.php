<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskWorklog;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_workspace_sees_an_empty_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('workspace_name', null)
                ->where('stats', [
                    'projects' => 0,
                    'my_open_tasks' => 0,
                    'overdue' => 0,
                    'due_soon' => 0,
                    'week_minutes' => 0,
                ])
                ->has('my_tasks', 0)
                ->has('dashboard_projects', 0)
                ->has('projects.items', 0)
                ->where('projects.can_create', false)
                ->has('activity', 0)
                ->etc());
    }

    public function test_dashboard_scopes_tasks_counts_and_activity_to_accessible_current_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['name' => 'Studio']);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'API']);
        $status = TaskStatus::factory()->create([
            'project_id' => $project->id,
            'category' => TaskStatusCategory::TODO,
        ]);
        $today = today();
        $overdue = Task::factory()->create([
            'project_id' => $project->id,
            'status_id' => $status->id,
            'number' => 1,
            'assignee_id' => $owner->id,
            'due_date' => $today->copy()->subDays(2),
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'status_id' => $status->id,
            'number' => 2,
            'assignee_id' => $owner->id,
            'due_date' => $today->copy()->addDays(3),
        ]);
        TaskMessage::factory()->create(['task_id' => $overdue->id, 'user_id' => $owner->id]);
        TaskWorklog::query()->create([
            'task_id' => $overdue->id,
            'user_id' => $owner->id,
            'work_date' => today(),
            'duration_minutes' => 90,
            'description' => '',
            'is_creative' => false,
        ]);
        TaskWorklog::query()->create([
            'task_id' => $overdue->id,
            'user_id' => $owner->id,
            'work_date' => today()->subWeek(),
            'duration_minutes' => 60,
            'description' => '',
            'is_creative' => false,
        ]);

        $otherWorkspace = Workspace::factory()->create();
        $otherProject = Project::factory()->create(['workspace_id' => $otherWorkspace->id, 'key' => 'OUT']);
        $otherTask = Task::factory()->create([
            'project_id' => $otherProject->id,
            'number' => 3,
            'assignee_id' => $owner->id,
            'due_date' => $today->copy()->subDay(),
        ]);

        TaskWorklog::query()->create([
            'task_id' => $otherTask->id,
            'user_id' => $owner->id,
            'work_date' => today(),
            'duration_minutes' => 210,
            'description' => '',
            'is_creative' => false,
        ]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('workspace_name', 'Studio')
                ->where('stats', [
                    'projects' => 1,
                    'my_open_tasks' => 2,
                    'overdue' => 1,
                    'due_soon' => 1,
                    'week_minutes' => 90,
                ])
                ->has('my_tasks', 2)
                ->where('my_tasks.0.reference', 'API-1')
                ->where('my_tasks.0.priority', 2)
                ->where('my_tasks.0.overdue', true)
                ->where('my_tasks.1.reference', 'API-2')
                ->has('dashboard_projects', 1)
                ->where('dashboard_projects.0.id', $project->id)
                ->where('dashboard_projects.0.total', 2)
                ->where('dashboard_projects.0.in_progress', 0)
                ->where('dashboard_projects.0.my_overdue', 1)
                ->where('dashboard_projects.0.progress_percent', 0)
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $project->id)
                ->where('projects.can_create', true)
                ->has('activity', 1)
                ->where('activity.0.reference', 'API-1')
                ->etc());
    }

    public function test_project_cards_use_the_same_progress_calculation_as_project_overview(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'APP']);
        $todo = TaskStatus::factory()->create(['project_id' => $project->id, 'category' => TaskStatusCategory::TODO]);
        $doing = TaskStatus::factory()->create(['project_id' => $project->id, 'category' => TaskStatusCategory::IN_PROGRESS]);
        $done = TaskStatus::factory()->create(['project_id' => $project->id, 'category' => TaskStatusCategory::DONE]);
        $canceled = TaskStatus::factory()->create(['project_id' => $project->id, 'category' => TaskStatusCategory::CANCELED]);
        foreach ([
            [$todo, $owner->id],
            [$doing, null],
            [$done, $owner->id],
            [$canceled, $owner->id],
        ] as $number => [$status, $assigneeId]) {
            Task::factory()->create([
                'project_id' => $project->id,
                'number' => $number + 1,
                'status_id' => $status->id,
                'assignee_id' => $assigneeId,
                'due_date' => today()->subDay(),
            ]);
        }

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('dashboard_projects', 1)
                ->where('dashboard_projects.0.id', $project->id)
                ->where('dashboard_projects.0.key', 'APP')
                ->where('dashboard_projects.0.total', 4)
                ->where('dashboard_projects.0.in_progress', 1)
                ->where('dashboard_projects.0.my_overdue', 1)
                ->where('dashboard_projects.0.progress_percent', 33)
                ->where('stats.overdue', 1)
                ->etc());
    }

    public function test_project_cards_show_at_most_four_accessible_projects_and_keep_sidebar_data(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $name) {
            Project::factory()->create(['workspace_id' => $workspace->id, 'name' => $name]);
        }
        Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Archived', 'archived_at' => now()]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('stats.projects', 5)
                ->has('dashboard_projects', 4)
                ->where('dashboard_projects.0.name', 'Five')
                ->where('dashboard_projects.1.name', 'Four')
                ->where('dashboard_projects.2.name', 'One')
                ->where('dashboard_projects.3.name', 'Three')
                ->has('projects.items', 5)
                ->etc());
    }

    public function test_dashboard_respects_selected_workspace_and_ignores_completed_or_archived_tasks(): void
    {
        $user = User::factory()->create();
        $first = Workspace::factory()->create();
        $second = Workspace::factory()->create();
        foreach ([$first, $second] as $workspace) {
            WorkspaceMember::factory()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::OWNER,
            ]);
        }

        $firstProject = Project::factory()->create(['workspace_id' => $first->id]);
        $secondProject = Project::factory()->create(['workspace_id' => $second->id]);
        $archivedProject = Project::factory()->create([
            'workspace_id' => $first->id,
            'archived_at' => now(),
        ]);
        $done = TaskStatus::factory()->create([
            'project_id' => $firstProject->id,
            'category' => TaskStatusCategory::DONE,
        ]);
        $todo = TaskStatus::factory()->create([
            'project_id' => $firstProject->id,
            'category' => TaskStatusCategory::TODO,
        ]);
        Task::factory()->create([
            'project_id' => $firstProject->id,
            'status_id' => $done->id,
            'assignee_id' => $user->id,
            'due_date' => today()->subDays(4),
        ]);
        Task::factory()->create([
            'project_id' => $firstProject->id,
            'status_id' => $todo->id,
            'assignee_id' => $user->id,
            'due_date' => today()->addDays(4),
        ]);
        Task::factory()->create([
            'project_id' => $archivedProject->id,
            'assignee_id' => $user->id,
        ]);
        Task::factory()->create([
            'project_id' => $secondProject->id,
            'assignee_id' => $user->id,
        ]);

        $this->actingAs($user)->withSession(['current_workspace_id' => $first->id])
            ->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('stats.projects', 1)
                ->where('stats.my_open_tasks', 1)
                ->where('stats.overdue', 0)
                ->where('stats.due_soon', 1)
                ->has('my_tasks', 1)
                ->has('dashboard_projects', 1)
                ->where('dashboard_projects.0.id', $firstProject->id)
                ->etc());

        $this->actingAs($user)->withSession(['current_workspace_id' => $second->id])
            ->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('stats.projects', 1)
                ->where('stats.my_open_tasks', 1)
                ->has('my_tasks', 1)
                ->etc());
    }

    public function test_project_only_member_sees_only_their_project_even_in_the_same_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $allowed = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'YES']);
        $hidden = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'NO']);
        ProjectMember::factory()->create([
            'project_id' => $allowed->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);
        $allowedTask = Task::factory()->create([
            'project_id' => $allowed->id,
            'assignee_id' => $member->id,
            'number' => 4,
        ]);
        TaskMessage::factory()->create(['task_id' => $allowedTask->id]);
        $hiddenTask = Task::factory()->create([
            'project_id' => $hidden->id,
            'assignee_id' => $member->id,
            'number' => 5,
        ]);
        TaskMessage::factory()->create(['task_id' => $hiddenTask->id]);
        foreach ([[$allowedTask, 45], [$hiddenTask, 300]] as [$workTask, $minutes]) {
            TaskWorklog::query()->create([
                'task_id' => $workTask->id,
                'user_id' => $member->id,
                'work_date' => today(),
                'duration_minutes' => $minutes,
                'description' => '',
                'is_creative' => false,
            ]);
        }

        $this->actingAs($member)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.projects', 1)
                ->where('stats.my_open_tasks', 1)
                ->where('stats.week_minutes', 45)
                ->has('dashboard_projects', 1)
                ->where('dashboard_projects.0.id', $allowed->id)
                ->where('dashboard_projects.0.total', 1)
                ->where('dashboard_projects.0.my_overdue', 0)
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $allowed->id)
                ->where('projects.can_create', false)
                ->has('my_tasks', 1)
                ->where('my_tasks.0.reference', 'YES-4')
                ->has('activity', 1)
                ->where('activity.0.reference', 'YES-4')
                ->etc());
    }

    public function test_activity_describes_actual_changes_and_collapses_repeated_events(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'ACT']);
        $task = Task::factory()->create(['project_id' => $project->id, 'number' => 11]);

        // Repeated status changes in the same task should not monopolize the feed.
        foreach (range(1, 3) as $offset) {
            TaskActivity::factory()->create([
                'task_id' => $task->id,
                'user_id' => $owner->id,
                'created_at' => now()->subMinutes($offset),
                'data' => [
                    'from' => ['name' => 'To do', 'category' => 1],
                    'to' => ['name' => 'In progress', 'category' => 2],
                ],
            ]);
        }
        TaskMessage::factory()->create([
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('activity', 2)
                ->where('activity.0.kind', 'status')
                ->where('activity.0.to', 'In progress')
                ->where('activity.0.reference', 'ACT-11')
                ->where('activity.1.kind', 'comment')
                ->where('activity.1.task_title', $task->title)
                ->etc());
    }

    public function test_summary_filters_and_more_tasks_are_scoped_and_paginated(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'FOC']);
        $todo = TaskStatus::factory()->create([
            'project_id' => $project->id,
            'category' => TaskStatusCategory::TODO,
        ]);
        $today = today();

        // The oldest task is low-priority, but an urgent overdue task should
        // take precedence over it in the attention view.
        Task::factory()->create([
            'project_id' => $project->id, 'status_id' => $todo->id,
            'number' => 1, 'assignee_id' => $owner->id,
            'priority' => TaskPriority::LOW, 'due_date' => $today->copy()->subDays(10),
        ]);
        Task::factory()->create([
            'project_id' => $project->id, 'status_id' => $todo->id,
            'number' => 2, 'assignee_id' => $owner->id,
            'priority' => TaskPriority::URGENT, 'due_date' => $today->copy()->subDays(2),
        ]);
        Task::factory()->create([
            'project_id' => $project->id, 'status_id' => $todo->id,
            'number' => 3, 'assignee_id' => $owner->id,
            'due_date' => $today->copy()->addDays(3),
        ]);
        Task::factory()->create([
            'project_id' => $project->id, 'status_id' => $todo->id,
            'number' => 4, 'assignee_id' => User::factory()->create()->id,
            'due_date' => $today->copy()->subDay(),
        ]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('task_view', 'attention')
                ->has('my_tasks', 3)
                ->where('my_tasks.0.reference', 'FOC-2')
                ->where('my_tasks.1.reference', 'FOC-1')
                ->where('task_pagination.next_url', null)
                ->etc());

        $this->actingAs($owner)->get(route('dashboard', ['focus' => 'overdue']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('task_view', 'overdue')
                ->where('stats.overdue', 2)
                ->has('my_tasks', 2)
                ->where('my_tasks.0.reference', 'FOC-2')
                ->etc());

        $this->actingAs($owner)->get(route('dashboard', ['focus' => 'due_soon']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('task_view', 'due_soon')
                ->has('my_tasks', 1)
                ->where('my_tasks.0.reference', 'FOC-3')
                ->etc());

        // More than fifteen open tasks should expose a subsequent page.
        foreach (range(5, 20) as $number) {
            Task::factory()->create([
                'project_id' => $project->id, 'status_id' => $todo->id,
                'number' => $number, 'assignee_id' => $owner->id,
                'due_date' => $today->copy()->addDays(10),
            ]);
        }
        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('my_tasks', 7)
                ->where('task_view', 'attention')
                ->etc());
        $this->actingAs($owner)->get(route('dashboard', ['focus' => 'open']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('my_tasks', 15)
                ->where('task_view', 'open')
                ->where('stats.my_open_tasks', 19)
                ->etc());
        $this->actingAs($owner)->get(route('dashboard', ['focus' => 'open', 'page' => 2]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('my_tasks', 4)
                ->where('task_view', 'open')
                ->etc());
    }

    public function test_activity_groups_distinct_edits_by_same_actor_but_preserves_older_changes(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'key' => 'EVE']);
        $task = Task::factory()->create(['project_id' => $project->id, 'number' => 1]);
        TaskActivity::factory()->create([
            'task_id' => $task->id, 'user_id' => $owner->id,
            'type' => TaskActivityType::STATUS_CHANGED,
            'created_at' => now()->subMinute(),
            'data' => ['from' => ['name' => 'To do'], 'to' => ['name' => 'In progress']],
        ]);
        TaskActivity::factory()->create([
            'task_id' => $task->id, 'user_id' => $owner->id,
            'type' => TaskActivityType::PRIORITY_CHANGED,
            'created_at' => now()->subMinutes(3),
            'data' => ['from' => ['value' => 2], 'to' => ['value' => 4]],
        ]);
        TaskActivity::factory()->create([
            'task_id' => $task->id, 'user_id' => $owner->id,
            'type' => TaskActivityType::STATUS_CHANGED,
            'created_at' => now()->subHour(),
            'data' => ['from' => ['name' => 'In progress'], 'to' => ['name' => 'Done']],
        ]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('activity', 2)
                ->has('activity.0.changes', 2)
                ->where('activity.0.changes.0.kind', 'status')
                ->where('activity.0.changes.1.kind', 'priority')
                ->where('activity.0.changes.1.to', '4')
                ->where('activity.1.to', 'Done')
                ->has('projects.items', 1)
                ->etc());
    }

}
