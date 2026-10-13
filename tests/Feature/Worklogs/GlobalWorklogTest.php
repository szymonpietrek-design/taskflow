<?php

namespace Tests\Feature\Worklogs;

use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskWorklog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GlobalWorklogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('worklogs.index'))->assertRedirect(route('login'));
    }

    public function test_worklogs_from_multiple_workspaces_are_aggregated_for_the_selected_date(): void
    {
        $user = User::factory()->create();
        $first = $this->makeProject($user);
        $second = $this->makeProject($user);
        $taskA = $this->makeTask($first);
        $taskB = $this->makeTask($second);
        $date = '2026-05-12';

        $this->log($taskA, $user, $date, 135, 'First task', true);
        $this->log($taskB, $user, $date, 165, null);
        $this->log($taskA, $user, '2026-05-11', 45, 'Previous day');
        $someoneElse = User::factory()->create();
        $this->log($taskA, $someoneElse, $date, 90, 'Foreign log');

        $this->actingAs($user)->get(route('worklogs.index', [
            'date' => $date,
            'timezone' => 'Europe/Warsaw',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('worklogs/Index')
            ->where('date', $date)
            ->where('timezone', 'Europe/Warsaw')
            ->where('daily_limit_minutes', 480)
            ->where('total_minutes', 300)
            ->where('visible_minutes', 300)
            ->where('entries.total', 2)
            ->has('entries.data', 2)
            ->where('entries.data.0.task.reference', $second->key.'-'.$taskB->number)
            ->where('entries.data.0.description', null)
            ->where('entries.data.1.is_creative', true)
        );

        $this->actingAs($user)->get(route('worklogs.index', ['date' => '2026-05-11']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('total_minutes', 45)->where('entries.total', 1));
    }

    public function test_worklog_keeps_sidebar_projects_scoped_to_current_workspace(): void
    {
        $user = User::factory()->create();
        $currentWorkspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $this->joinWorkspace($user, $currentWorkspace, WorkspaceRole::ADMIN);
        $this->joinWorkspace($user, $otherWorkspace, WorkspaceRole::ADMIN);

        $currentProject = $this->makeProject($user, $currentWorkspace);
        $otherProject = $this->makeProject($user, $otherWorkspace);
        $currentProject->update(['name' => 'Alpha Project']);
        $otherProject->update(['name' => 'Beta Project']);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $currentWorkspace->id])
            ->get(route('worklogs.index', ['date' => '2026-05-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('worklogs/Index')
                ->where('workspace.current.id', $currentWorkspace->id)
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $currentProject->id)
                ->has('worklog_projects', 2)
                ->where('worklog_projects.0.id', $currentProject->id)
                ->where('worklog_projects.1.id', $otherProject->id));
    }

    public function test_private_projects_and_removed_memberships_cannot_leak_details(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->joinWorkspace($user, $workspace, WorkspaceRole::MEMBER);
        $public = $this->makeProject($user, $workspace);
        $private = $this->makeProject($user, $workspace, ProjectVisibility::PRIVATE);
        $publicTask = $this->makeTask($public);
        $privateTask = $this->makeTask($private);

        $this->log($publicTask, $user, '2026-05-12', 60, 'Public entry');
        $this->log($privateTask, $user, '2026-05-12', 120, 'Private entry');
        $url = route('worklogs.index', ['date' => '2026-05-12']);

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('total_minutes', 180)
            ->where('visible_minutes', 60)
            ->where('entries.total', 1)
            ->where('entries.data.0.project.id', $public->id));

        $membership = ProjectMember::factory()->create([
            'project_id' => $private->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visible_minutes', 180)
            ->where('entries.total', 2));

        $membership->delete();
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visible_minutes', 60)->where('entries.total', 1));

        // Losing the workspace membership hides even workspace-visible project details.
        WorkspaceMember::query()->where('user_id', $user->id)->where('workspace_id', $workspace->id)->delete();
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('total_minutes', 180)
            ->where('visible_minutes', 0)
            ->where('entries.total', 0)
            ->has('entries.data', 0));
    }

    public function test_workspace_admin_can_see_their_own_entries_in_private_projects(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->joinWorkspace($admin, $workspace, WorkspaceRole::ADMIN);
        $private = $this->makeProject($admin, $workspace, ProjectVisibility::PRIVATE);
        $task = $this->makeTask($private);
        $this->log($task, $admin, '2026-05-12', 90);

        $this->actingAs($admin)->get(route('worklogs.index', ['date' => '2026-05-12']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visible_minutes', 90)
            ->where('entries.total', 1));
    }

    public function test_daily_total_is_not_limited_to_the_current_page(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProject($user);
        $task = $this->makeTask($project);
        for ($i = 0; $i < 18; $i++) {
            $this->log($task, $user, '2026-05-12', 10);
        }

        $url = route('worklogs.index', ['date' => '2026-05-12', 'page' => 2]);
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('total_minutes', 180)
            ->where('visible_minutes', 180)
            ->where('entries.total', 18)
            ->where('entries.current_page', 2)
            ->where('entries.last_page', 2)
            ->has('entries.data', 3));
    }

    public function test_default_date_uses_the_supplied_browser_timezone(): void
    {
        $user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-10-10 23:30:00', 'UTC'));
        try {
            $this->actingAs($user)->get(route('worklogs.index', ['timezone' => 'Europe/Warsaw']))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-11'));
            $this->actingAs($user)->get(route('worklogs.index'))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-10'));
        } finally {
            $this->travelBack();
        }
    }

    public function test_invalid_query_parameters_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('worklogs.index', ['date' => '2026-99-99']))
            ->assertSessionHasErrors('date');
        $this->actingAs($user)->get(route('worklogs.index', ['timezone' => 'Not/AZone']))
            ->assertSessionHasErrors('timezone');
    }

    public function test_week_bar_includes_time_from_multiple_projects_and_counts_inaccessible_time_without_leaking_details(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->joinWorkspace($user, $workspace, WorkspaceRole::MEMBER);
        $public = $this->makeProject($user, $workspace);
        $private = $this->makeProject($user, $workspace, ProjectVisibility::PRIVATE);
        $this->log($this->makeTask($public), $user, '2026-05-11', 60);
        $this->log($this->makeTask($private), $user, '2026-05-12', 125);
        $this->log($this->makeTask($public), $user, '2026-05-12', 45);

        $this->actingAs($user)->get(route('worklogs.index', ['date' => '2026-05-12']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('week', 7)
            ->where('week.0.date', '2026-05-11')
            ->where('week.0.total_minutes', 60)
            ->where('week.1.date', '2026-05-12')
            ->where('week.1.total_minutes', 170)
            ->where('total_minutes', 170)
            ->where('visible_minutes', 45)
            ->where('entries.total', 1));
    }

    public function test_global_worklog_can_search_tasks_and_create_edit_delete_own_entries(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->joinWorkspace($user, $workspace, WorkspaceRole::ADMIN);
        $project = $this->makeProject($user, $workspace);
        $task = $this->makeTask($project);
        $otherTask = $this->makeTask($project);
        $task->update(['title' => 'Implement login flow']);
        $otherTask->update(['title' => 'Documentation']);
        $date = '2026-05-12';

        $this->actingAs($user)->get(route('worklogs.index', ['date' => $date]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('worklog_projects', 1)
            ->where('worklog_projects.0.id', $project->id));

        $this->actingAs($user)->get(route('worklogs.tasks', [$project, 'search' => 'login']))
            ->assertOk()->assertJsonCount(1, 'tasks')->assertJsonPath('tasks.0.id', $task->id);

        $this->actingAs($user)->post(route('worklogs.store'), [
            'task_id' => $task->id, 'work_date' => $date, 'timezone' => 'Europe/Warsaw',
            'hours' => 2, 'minutes' => 15, 'description' => '  Initial implementation  ', 'is_creative' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('worklogs.index', [
            'date' => $date, 'timezone' => 'Europe/Warsaw',
        ]));
        $log = $task->worklogs()->sole();
        $this->assertSame(135, $log->duration_minutes);
        $this->assertSame('Initial implementation', $log->description);
        $this->assertTrue($log->is_creative);

        $this->actingAs($user)->get(route('worklogs.index', ['date' => $date]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.can_manage', true)
            ->where('entries.data.0.work_date', $date));

        $this->actingAs($user)->patch(route('worklogs.update', $log), [
            'work_date' => $date, 'timezone' => 'Europe/Warsaw',
            'hours' => 1, 'minutes' => 30, 'description' => '  ', 'is_creative' => false,
        ])->assertSessionHasNoErrors();
        $this->assertSame(90, $log->fresh()->duration_minutes);
        $this->assertNull($log->fresh()->description);
        $this->assertFalse($log->fresh()->is_creative);

        // Editing is not allowed to silently reassign the entry to another task.
        $this->actingAs($user)->patch(route('worklogs.update', $log), [
            'task_id' => $otherTask->id, 'work_date' => $date,
            'hours' => 1, 'minutes' => 0,
        ])->assertSessionHasErrors('task_id');
        $this->assertSame($task->id, $log->fresh()->task_id);

        $this->actingAs($user)->delete(route('worklogs.destroy', $log), ['timezone' => 'Europe/Warsaw'])
            ->assertRedirect(route('worklogs.index', ['date' => $date, 'timezone' => 'Europe/Warsaw']));
        $this->assertDatabaseMissing('task_worklogs', ['id' => $log->id]);
    }

    public function test_global_creation_and_editing_share_daily_limit_across_projects(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->joinWorkspace($user, $workspace, WorkspaceRole::ADMIN);
        $firstTask = $this->makeTask($this->makeProject($user, $workspace));
        $secondTask = $this->makeTask($this->makeProject($user, $workspace));
        $date = '2026-05-12';
        $this->log($firstTask, $user, $date, 420);
        $payload = ['task_id' => $secondTask->id, 'work_date' => $date, 'hours' => 2, 'minutes' => 0];

        $this->actingAs($user)->post(route('worklogs.store'), $payload)->assertSessionHasErrors('hours');
        $this->assertSame(0, $secondTask->worklogs()->count());
        $this->actingAs($user)->post(route('worklogs.store'), [
            ...$payload, 'hours' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(480, (int) TaskWorklog::query()->where('user_id', $user->id)->sum('duration_minutes'));
        $log = $secondTask->worklogs()->sole();

        $this->actingAs($user)->patch(route('worklogs.update', $log), [
            'work_date' => $date, 'hours' => 2, 'minutes' => 0,
        ])->assertSessionHasErrors('hours');
        $this->assertSame(60, $log->fresh()->duration_minutes);
    }

    public function test_global_worklogs_cannot_be_modified_without_permissions(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->joinWorkspace($owner, $workspace, WorkspaceRole::ADMIN);
        $project = $this->makeProject($owner, $workspace);
        $task = $this->makeTask($project);
        $log = $this->log($task, $owner, '2026-05-12', 60);
        $payload = ['task_id' => $task->id, 'work_date' => '2026-05-12', 'hours' => 1, 'minutes' => 0];

        $this->actingAs($stranger)->get(route('worklogs.tasks', $project))->assertForbidden();
        $this->actingAs($stranger)->post(route('worklogs.store'), $payload)->assertForbidden();
        $this->actingAs($stranger)->patch(route('worklogs.update', $log), $payload)->assertForbidden();
        $this->actingAs($stranger)->delete(route('worklogs.destroy', $log))->assertForbidden();
        $this->assertSame(60, $log->fresh()->duration_minutes);

        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->get(route('worklogs.tasks', $project))->assertForbidden();
        $this->actingAs($owner)->post(route('worklogs.store'), $payload)->assertForbidden();
        $this->actingAs($owner)->patch(route('worklogs.update', $log), $payload)->assertForbidden();
        $this->actingAs($owner)->delete(route('worklogs.destroy', $log))->assertForbidden();
    }

    private function makeProject(User $user, ?Workspace $workspace = null, ProjectVisibility $visibility = ProjectVisibility::WORKSPACE): Project
    {
        $workspace ??= Workspace::factory()->create();
        if (! WorkspaceMember::query()->where('user_id', $user->id)->where('workspace_id', $workspace->id)->exists()) {
            $this->joinWorkspace($user, $workspace, WorkspaceRole::MEMBER);
        }

        return Project::factory()->create([
            'workspace_id' => $workspace->id,
            'visibility' => $visibility,
        ]);
    }

    private function joinWorkspace(User $user, Workspace $workspace, WorkspaceRole $role): void
    {
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    private function makeTask(Project $project): Task
    {
        return Task::factory()->create(['project_id' => $project->id]);
    }

    private function log(Task $task, User $user, string $date, int $minutes, ?string $description = null, bool $creative = false): TaskWorklog
    {
        return $task->worklogs()->create([
            'user_id' => $user->id,
            'work_date' => $date,
            'duration_minutes' => $minutes,
            'description' => $description,
            'is_creative' => $creative,
        ]);
    }
}
