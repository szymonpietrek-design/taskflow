<?php

namespace Tests\Feature\Projects;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTeamAccessTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Development');
        $first = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $second = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        return [$owner, $workspace, $first, $second];
    }

    public function test_project_only_member_sees_their_workspace_but_only_assigned_project(): void
    {
        [$owner, $workspace, $first, $second] = $this->setupWorkspace();
        $collaborator = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $first->id, 'user_id' => $collaborator->id]);
        $this->assertFalse($collaborator->workspaceMemberships()->exists());
        $this->assertTrue($collaborator->can('view', $workspace));
        $this->assertTrue($collaborator->can('view', $first));
        $this->assertFalse($collaborator->can('view', $second));
        $this->actingAs($collaborator)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current.id', $workspace->id)
                ->where('workspace.current.role', 'member')
                ->has('projects.items', 1)
                ->where('projects.items.0.id', $first->id));
        $this->get(route('projects.show', $second))->assertForbidden();
    }

    public function test_owner_and_administrator_inherit_access_without_project_membership(): void
    {
        [$owner, $workspace, $first, $second] = $this->setupWorkspace();
        $admin = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id, 'role' => WorkspaceRole::ADMIN]);
        $this->assertTrue($owner->can('view', $first));
        $this->assertTrue($admin->can('view', $second));
        $this->assertTrue($admin->can('manageManagers', $second));
        $this->assertFalse($admin->projectMemberships()->exists());
    }

    public function test_suspended_workspace_administrator_cannot_fall_back_to_old_project_grants(): void
    {
        [$owner, $workspace, $first, $second] = $this->setupWorkspace();
        $administrator = User::factory()->create();
        $grant = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $administrator->id,
            'role' => WorkspaceRole::ADMIN,
        ]);
        // A legacy grant is possible in imported data, even though the new UI
        // removes duplicates when appointing an administrator.
        ProjectMember::factory()->create([
            'project_id' => $first->id,
            'user_id' => $administrator->id,
            'role' => ProjectRole::MANAGER,
        ]);
        $this->assertTrue($administrator->can('view', $first));
        $this->assertTrue($administrator->can('view', $second));

        $grant->update(['suspended_at' => now()]);
        $this->assertFalse($administrator->can('view', $first));
        $this->assertFalse($administrator->can('view', $second));
        $this->actingAs($administrator)->get(route('projects.show', $first))->assertForbidden();

        $grant->update(['suspended_at' => null]);
        $this->assertTrue($administrator->can('view', $first));
    }

    public function test_manager_can_add_regular_project_member_but_cannot_appoint_manager(): void
    {
        [$owner, $workspace, $first] = $this->setupWorkspace();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $first->id, 'user_id' => $manager->id, 'role' => ProjectRole::MANAGER]);
        $this->actingAs($manager)->post(route('projects.members.store', $first), [
            'user_id' => $member->id, 'role' => 'manager',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('project_members', ['project_id' => $first->id, 'user_id' => $member->id]);
        $this->post(route('projects.members.store', $first), [
            'user_id' => $member->id, 'role' => 'member',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($member->can('view', $first));
        $this->assertFalse($member->can('manageMembers', $first));
        $this->assertTrue($member->can('create', [Task::class, $first]));
    }

    public function test_only_workspace_administrator_can_assign_manager_role(): void
    {
        [$owner, $workspace, $first] = $this->setupWorkspace();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $first->id, 'user_id' => $manager->id, 'role' => ProjectRole::MANAGER]);
        ProjectMember::factory()->create(['project_id' => $first->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER]);
        $membership = $member->projectMemberships()->sole();
        $this->actingAs($manager)->patch(route('projects.members.update', [$first, $membership]), ['role' => 'manager'])
            ->assertForbidden();
        $this->actingAs($owner)->patch(route('projects.members.update', [$first, $membership]), ['role' => 'manager'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->can('manageMembers', $first));
    }

    public function test_team_removal_revokes_access_without_deleting_work_history(): void
    {
        [$owner, $workspace, $first, $second] = $this->setupWorkspace();
        $member = User::factory()->create();
        $grant = ProjectMember::factory()->create(['project_id' => $first->id, 'user_id' => $member->id]);
        ProjectMember::factory()->create(['project_id' => $second->id, 'user_id' => $member->id]);
        $task = Task::factory()->create(['project_id' => $first->id, 'created_by' => $member->id]);
        $this->actingAs($owner)->delete(route('projects.members.destroy', [$first, $grant]))->assertSessionHasNoErrors();
        $this->assertFalse($member->can('view', $first));
        $this->assertFalse($member->can('view', $task));
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'created_by' => $member->id]);
        $this->assertTrue($member->can('view', $second));
    }

    public function test_outsider_cannot_search_or_change_project_members(): void
    {
        [$owner, $workspace, $first] = $this->setupWorkspace();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('projects.members.index', $first))->assertForbidden();
        $this->get(route('projects.members.candidates', $first))->assertForbidden();
        $this->post(route('projects.members.store', $first), ['user_id' => $outsider->id, 'role' => 'member'])
            ->assertForbidden();
    }

    public function test_candidate_search_excludes_disabled_users_and_workspace_administrators(): void
    {
        [$owner, $workspace, $first] = $this->setupWorkspace();
        $eligible = User::factory()->create(['name' => 'Visible Candidate']);
        $disabled = User::factory()->create(['name' => 'Disabled Candidate', 'disabled_at' => now()]);
        $this->actingAs($owner)->getJson(route('projects.members.candidates', $first))
            ->assertOk()->assertJsonFragment(['id' => $eligible->id])
            ->assertJsonMissing(['id' => $disabled->id])->assertJsonMissing(['id' => $owner->id]);
    }

    public function test_archived_project_is_not_reachable_by_regular_members_using_a_direct_url(): void
    {
        [$owner, $workspace, $first] = $this->setupWorkspace();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $first->id, 'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $first->id, 'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);
        $task = Task::factory()->create(['project_id' => $first->id]);
        $first->forceFill(['archived_at' => now()])->save();

        $this->assertFalse($member->can('view', $first));
        $this->assertFalse($member->can('view', $task));
        $this->actingAs($member)->get(route('projects.show', $first))->assertForbidden();
        $this->get(route('tasks.show', [$first, $task]))->assertForbidden();
        $this->get(route('reports.index', $first))->assertForbidden();
        $this->get(route('projects.members.index', $first))->assertForbidden();
        $this->assertTrue($manager->can('view', $first));
        $this->assertTrue($owner->can('view', $first));
        $this->actingAs($manager)->get(route('projects.show', $first))->assertOk();
    }

    public function test_archived_project_team_cannot_be_modified(): void
    {
        [$owner, $workspace, $first] = $this->setupWorkspace();
        $first->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->post(route('projects.members.store', $first), [
            'user_id' => User::factory()->create()->id, 'role' => 'member',
        ])->assertForbidden();
    }
}
