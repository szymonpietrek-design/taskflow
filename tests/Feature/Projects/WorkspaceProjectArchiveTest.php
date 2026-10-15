<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceProjectArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_archive_and_restore_without_deleting_tasks_or_statuses(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Customer API', 'CAPI');
        $task = app(CreateTask::class)->handle($owner, $project, 'Implement endpoint');
        $slug = $project->slug;
        $statuses = $project->taskStatuses()->count();

        $this->actingAs($owner)->patch(route('workspaces.projects.archive', [$workspace, $project]))
            ->assertRedirect(route('workspaces.index'));

        $this->assertNotNull($project->fresh()->archived_at);
        $this->assertSame($slug, $project->fresh()->slug);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $project->id]);
        $this->assertSame($statuses, $project->taskStatuses()->count());
        $this->actingAs($owner)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("projectsByWorkspace.{$workspace->id}", 0)
                ->has("archivedProjectsByWorkspace.{$workspace->id}", 1)
                ->where("archivedProjectsByWorkspace.{$workspace->id}.0.id", $project->id)
                ->where("archivedProjectsByWorkspace.{$workspace->id}.0.can_archive", true)
                ->etc());

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('project.is_archived', true)->where('project.can_manage', false));
        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('projects.items', 0));

        // Retrying the archive operation is safe and does not remove any data.
        $this->actingAs($owner)->patch(route('workspaces.projects.archive', [$workspace, $project]))->assertRedirect();
        $this->actingAs($owner)->patch(route('workspaces.projects.restore', [$workspace, $project]))
            ->assertRedirect(route('workspaces.index'));

        $this->assertNull($project->fresh()->archived_at);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->actingAs($owner)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("projectsByWorkspace.{$workspace->id}", 1)
                ->has("archivedProjectsByWorkspace.{$workspace->id}", 0)
                ->etc());
    }

    public function test_archived_project_is_read_only_for_tasks_statuses_and_project_details(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'App', 'APP');
        $task = app(CreateTask::class)->handle($owner, $project, 'Existing task');

        $this->actingAs($owner)->patch(route('workspaces.projects.archive', [$workspace, $project]))->assertRedirect();
        $this->actingAs($owner)->patch(route('workspaces.projects.update', [$workspace, $project]), [
            'name' => 'Changed', 'visibility' => 1,
        ])->assertForbidden();
        $this->actingAs($owner)->post(route('projects.statuses.store', $project), [
            'name' => 'Blocked', 'category' => 1,
        ])->assertForbidden();
        $this->actingAs($owner)->get(route('projects.statuses.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_manage', false));
        $this->actingAs($owner)->get(route('tasks.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_create', false));
        $this->actingAs($owner)->patch(route('tasks.status.update', [$project, $task]), [
            'status_id' => $task->status_id,
        ])->assertForbidden();
        $this->assertFalse($owner->can('update', $task));
        $this->assertFalse($owner->can('delete', $task));
        $this->assertFalse($owner->can('create', [\App\Models\Task::class, $project->fresh()]));
        $this->assertDatabaseMissing('task_statuses', ['project_id' => $project->id, 'name' => 'Blocked']);
    }

    public function test_only_workspace_admins_and_project_managers_can_archive_and_restore(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        $other = app(CreateWorkspace::class)->handle($owner, 'Other');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'App', 'APP');
        foreach ([[$admin, WorkspaceRole::ADMIN], [$manager, WorkspaceRole::MEMBER], [$member, WorkspaceRole::MEMBER]] as [$user, $role]) {
            WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);
        }
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => ProjectRole::MANAGER]);

        foreach ([$member, $outsider] as $user) {
            $this->actingAs($user)->patch(route('workspaces.projects.archive', [$workspace, $project]))->assertForbidden();
            $this->actingAs($user)->patch(route('workspaces.projects.restore', [$workspace, $project]))->assertForbidden();
        }
        $this->actingAs($owner)->patch(route('workspaces.projects.archive', [$other, $project]))->assertForbidden();
        $this->assertNull($project->fresh()->archived_at);

        $this->actingAs($admin)->patch(route('workspaces.projects.archive', [$workspace, $project]))->assertRedirect();
        $this->actingAs($manager)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("archivedProjectsByWorkspace.{$workspace->id}", 1)->etc());
        $this->actingAs($member)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("archivedProjectsByWorkspace.{$workspace->id}", 0)->etc());
        $this->actingAs($manager)->patch(route('workspaces.projects.restore', [$workspace, $project]))->assertRedirect();
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_suspended_manager_cannot_find_archived_projects_or_restore_them(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Private', 'PRIVATE');
        $membership = WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $manager->id, 'role' => WorkspaceRole::MEMBER]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $manager->id, 'role' => ProjectRole::MANAGER]);
        $this->actingAs($owner)->patch(route('workspaces.projects.archive', [$workspace, $project]))->assertRedirect();
        $membership->update(['suspended_at' => now()]);

        $this->actingAs($manager)->patch(route('workspaces.projects.restore', [$workspace, $project]))->assertForbidden();
        $this->actingAs($manager)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("archivedProjectsByWorkspace.{$workspace->id}", 0)->etc());
    }
}
