<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectType;
use App\Enums\ProjectRole;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\TaskWorklog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_overview_counts_tasks_and_time_shows_recent_events_and_latest_published_release(): void
    {
        [$user, $project] = $this->makeProject(ProjectType::APPLICATION);
        $todo = $this->createTaskStatus($project, 'Todo', TaskStatusCategory::TODO);
        $doing = $this->createTaskStatus($project, 'Working', TaskStatusCategory::IN_PROGRESS);
        $done = $this->createTaskStatus($project, 'Done', TaskStatusCategory::DONE);
        $canceled = $this->createTaskStatus($project, 'Canceled', TaskStatusCategory::CANCELED);

        $task = $this->task($project, $user, $done, 1);
        $this->task($project, $user, $doing, 2);
        $this->task($project, $user, $todo, 3);
        $this->task($project, $user, $canceled, 4);

        TaskWorklog::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'duration_minutes' => 95,
            'description' => 'Testing',
            'is_creative' => false,
        ]);
        TaskMessage::factory()->create(['task_id' => $task->id, 'user_id' => $user->id]);
        TaskActivity::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivityType::STATUS_CHANGED,
        ]);

        Release::factory()->create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'version' => '1.2.0',
            'version_major' => 1,
            'version_minor' => 2,
            'version_patch' => 0,
            'status' => ReleaseStatus::RELEASED,
            'released_at' => now(),
        ]);
        Release::factory()->create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'version' => '2.0.0',
            'version_major' => 2,
            'version_minor' => 0,
            'version_patch' => 0,
            'status' => ReleaseStatus::DRAFT,
        ]);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Show')
                ->where('can_create_task', true)
                ->where('can_manage_team', true)
                ->where('can_manage_managers', true)
                ->where('overview.stats.total', 4)
                ->where('overview.stats.in_progress', 1)
                ->where('overview.stats.completed', 1)
                ->where('overview.stats.worklog_minutes', 95)
                ->where('overview.stats.progress_percent', 33)
                ->has('overview.recent_tasks', 4)
                ->has('overview.activity', 3)
                ->where('overview.latest_release.version', '1.2.0'),
            );
    }

    public function test_team_preview_shows_active_administrators_and_project_members_without_duplicates(): void
    {
        [$owner, $project] = $this->makeProject();
        $admin = User::factory()->create(['name' => 'Admin User']);
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        // Legacy duplicate grants must not make inherited admins appear twice.
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $suspended = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $suspended->id,
            'role' => WorkspaceRole::ADMIN,
            'suspended_at' => now(),
        ]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $suspended->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $disabled = User::factory()->create();
        $disabled->forceFill(['disabled_at' => now()])->save();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $disabled->id,
            'role' => ProjectRole::MEMBER,
        ]);

        foreach (range(1, 4) as $index) {
            $user = User::factory()->create(['name' => "Member {$index}"]);
            ProjectMember::factory()->create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => $index === 1 ? ProjectRole::MANAGER : ProjectRole::MEMBER,
            ]);
        }

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Show')
                ->where('team_preview.total', 6)
                ->where('team_preview.remaining', 0)
                ->has('team_preview.members', 6)
                ->where('team_preview.members.0.id', $owner->id)
                ->where('team_preview.members.0.role', 'owner')
                ->where('team_preview.members.1.id', $admin->id)
                ->where('team_preview.members.1.role', 'admin')
                ->where('team_preview.members.2.role', 'manager')
                ->has('team_preview.overflow_members', 0)
                ->where('team_preview.unlisted_count', 0)
                ->etc());
    }

    public function test_team_preview_limits_tooltip_names_for_large_teams(): void
    {
        [$owner, $project] = $this->makeProject();

        foreach (range(1, 18) as $index) {
            $user = User::factory()->create(['name' => "Member {$index}"]);
            ProjectMember::factory()->create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => ProjectRole::MEMBER,
            ]);
        }

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team_preview.total', 19)
                ->has('team_preview.members', 8)
                ->has('team_preview.overflow_members', 6)
                ->where('team_preview.remaining', 11)
                ->where('team_preview.unlisted_count', 5)
                ->etc());
    }

    public function test_overview_does_not_include_records_from_other_projects(): void
    {
        [$user, $project] = $this->makeProject();
        [$otherUser, $otherProject] = $this->makeProject();
        $status = $this->createTaskStatus($otherProject, 'Done', TaskStatusCategory::DONE);
        $otherTask = $this->task($otherProject, $otherUser, $status, 1);
        TaskMessage::factory()->create(['task_id' => $otherTask->id, 'user_id' => $otherUser->id]);
        TaskWorklog::query()->create([
            'task_id' => $otherTask->id,
            'user_id' => $otherUser->id,
            'work_date' => now()->toDateString(),
            'duration_minutes' => 60,
            'is_creative' => false,
        ]);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.stats.total', 0)
                ->where('overview.stats.worklog_minutes', 0)
                ->where('overview.stats.progress_percent', 0)
                ->has('overview.recent_tasks', 0)
                ->has('overview.activity', 0)
                ->where('overview.latest_release', null),
            );

        $this->actingAs($user)->get(route('projects.show', $otherProject))->assertForbidden();
    }

    public function test_archived_project_is_read_only_in_overview(): void
    {
        [$user, $project] = $this->makeProject();
        $project->forceFill(['archived_at' => now()])->save();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.is_archived', true)
                ->where('can_create_task', false)
                ->where('task_form', null),
            );
    }

    public function test_project_overview_shares_member_management_permissions_by_role(): void
    {
        [$owner, $project] = $this->makeProject();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('can_manage_team', true)
                ->where('can_manage_managers', true)->etc());
        $this->actingAs($manager)->get(route('projects.show', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('can_manage_team', true)
                ->where('can_manage_managers', false)->etc());
        $this->actingAs($member)->get(route('projects.show', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('can_manage_team', false)
                ->where('can_manage_managers', false)->etc());

        $project->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('can_manage_team', false)
                ->where('can_manage_managers', false)->etc());
    }

    public function test_task_creation_dialog_receives_only_current_project_options(): void
    {
        [$owner, $project] = $this->makeProject();
        $status = $this->createTaskStatus($project, 'Ready', TaskStatusCategory::TODO);
        $parent = $this->task($project, $owner, $status, 1);
        $child = $this->task($project, $owner, $status, 2);
        $child->forceFill(['parent_id' => $parent->id])->save();

        [$otherOwner, $otherProject] = $this->makeProject();
        $otherStatus = $this->createTaskStatus($otherProject, 'Other status', TaskStatusCategory::TODO);
        $this->task($otherProject, $otherOwner, $otherStatus, 1);

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Show')
                ->where('can_create_task', true)
                ->has('task_form.statuses', 1)
                ->where('task_form.statuses.0.id', $status->id)
                ->has('task_form.assignees', 1)
                ->where('task_form.assignees.0.id', $owner->id)
                ->has('task_form.parent_tasks', 1)
                ->where('task_form.parent_tasks.0.id', $parent->id),
            );

        $this->actingAs($owner)->get(route('tasks.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_create', true)
                ->has('statuses', 1)
                ->where('statuses.0.id', $status->id)
                ->has('parent_tasks', 1)
                ->where('parent_tasks.0.id', $parent->id),
            );
    }

    public function test_legacy_workspace_member_cannot_open_project_overview(): void
    {
        [$owner, $project] = $this->makeProject();
        $member = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($member)->get(route('projects.show', $project))->assertForbidden();
    }

    /** @return array{User, Project} */
    private function makeProject(ProjectType $type = ProjectType::STANDARD): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $user->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => $type,
        ]);

        return [$user, $project];
    }

    private function createTaskStatus(Project $project, string $name, TaskStatusCategory $category): TaskStatus
    {
        return TaskStatus::factory()->create([
            'project_id' => $project->id,
            'name' => $name,
            'category' => $category,
        ]);
    }

    private function task(Project $project, User $user, TaskStatus $status, int $number): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'number' => $number,
            'status_id' => $status->id,
        ]);
    }
}
