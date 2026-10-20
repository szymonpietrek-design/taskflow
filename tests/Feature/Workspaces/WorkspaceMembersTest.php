<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Workspace administration is restricted to owner/admin.
 * Regular collaborators receive access only through project_members.
 */
class WorkspaceMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_workspace_administrators_not_legacy_members(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create(['name' => 'Anna Admin']);
        $legacy = User::factory()->create(['name' => 'Legacy Member']);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $legacy->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Members')
                ->where('can_assign_admin', true)
                ->where('members.total', 2)
                ->where('members.data.0.role', 'owner')
                ->where('members.data.1.role', 'admin')
                ->missing('members.data.2')
                ->etc());
    }

    public function test_administrator_can_view_list_but_cannot_assign_another_administrator(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create();
        $candidate = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($admin)->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_assign_admin', false)->etc());
        $this->getJson(route('workspaces.members.candidates', $workspace))->assertForbidden();
        $this->post(route('workspaces.members.store', $workspace), [
            'email' => $candidate->email, 'role' => 'admin',
        ])->assertForbidden();
        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id, 'user_id' => $candidate->id,
        ]);
    }

    public function test_project_member_cannot_access_workspace_administrator_management(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $collaborator = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $collaborator->id]);

        $this->assertTrue($collaborator->can('view', $workspace));
        $this->actingAs($collaborator)->get(route('workspaces.members.index', $workspace))->assertForbidden();
        $this->getJson(route('workspaces.members.candidates', $workspace))->assertForbidden();
        $this->post(route('workspaces.members.store', $workspace), [
            'email' => User::factory()->create()->email, 'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_administrator_list_search_is_workspace_scoped_and_excludes_legacy_roles(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $match = User::factory()->create(['name' => 'Jan Administrator', 'email' => 'jan@example.test']);
        $legacy = User::factory()->create(['name' => 'Jan Legacy']);
        $outsideOwner = User::factory()->create(['name' => 'Jan Other Workspace']);
        app(CreateWorkspace::class)->handle($outsideOwner, 'Other Team');
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $match->id,
            'role' => WorkspaceRole::ADMIN,
        ]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $legacy->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)
            ->get(route('workspaces.members.index', ['workspace' => $workspace, 'q' => 'jan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'jan')
                ->where('members.total', 1)
                ->has('members.data', 1)
                ->where('members.data.0.name', $match->name)
                ->where('members.data.0.role', 'admin')
                ->etc());
    }

    public function test_administrator_list_is_paginated(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        foreach (range(1, 22) as $index) {
            WorkspaceMember::factory()->create([
                'workspace_id' => $workspace->id,
                'user_id' => User::factory()->create()->id,
                'role' => WorkspaceRole::ADMIN,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.total', 23)
                ->has('members.data', 20)
                ->where('members.current_page', 1)
                ->where('members.last_page', 2)
                ->etc());
    }

    public function test_owner_candidate_picker_ignores_disabled_accounts_and_existing_memberships(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $candidate = User::factory()->create(['name' => 'Alice Candidate']);
        $disabled = User::factory()->create(['name' => 'Alice Disabled', 'disabled_at' => now()]);
        $existing = User::factory()->create(['name' => 'Alice Admin']);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $existing->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($owner)
            ->getJson(route('workspaces.members.candidates', $workspace).'?q=Alice')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $candidate->id)
            ->assertJsonMissing(['id' => $disabled->id])
            ->assertJsonMissing(['id' => $existing->id]);
    }

    public function test_candidate_picker_handles_empty_search_and_limits_results(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        User::factory()->count(14)->create(['name' => 'Candidate']);

        $this->actingAs($owner)
            ->getJson(route('workspaces.members.candidates', $workspace).'?q=')
            ->assertOk()
            ->assertJsonCount(12, 'users')
            ->assertJsonPath('has_more', true);
        $this->getJson(route('workspaces.members.candidates', [
            'workspace' => $workspace, 'q' => str_repeat('x', 101),
        ]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_owner_can_assign_admin_but_not_workspace_member_role(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $candidate = User::factory()->create(['email' => 'admin@example.test']);

        $this->actingAs($owner)->post(route('workspaces.members.store', $workspace), [
            'email' => ' ADMIN@EXAMPLE.TEST ', 'role' => 'member',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id, 'user_id' => $candidate->id,
        ]);

        $this->post(route('workspaces.members.store', $workspace), [
            'email' => ' ADMIN@EXAMPLE.TEST ', 'role' => 'admin',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $candidate->id,
            'role' => WorkspaceRole::ADMIN->value,
        ]);
    }

    public function test_owner_cannot_add_blocked_account_or_duplicate_admin(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $blocked = User::factory()->create(['disabled_at' => now()]);
        $admin = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($owner)->post(route('workspaces.members.store', $workspace), [
            'email' => $blocked->email, 'role' => 'admin',
        ])->assertSessionHasErrors('email');
        $this->post(route('workspaces.members.store', $workspace), [
            'email' => $admin->email, 'role' => 'admin',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id, 'user_id' => $blocked->id,
        ]);
    }

    public function test_assigning_workspace_admin_removes_redundant_project_grants_in_that_workspace_only(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $otherWorkspace = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Other Team');
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id, 'created_by' => $owner->id,
        ]);
        $otherProject = Project::factory()->create(['workspace_id' => $otherWorkspace->id]);
        $candidate = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $candidate->id,
            'role' => ProjectRole::MANAGER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $otherProject->id, 'user_id' => $candidate->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($owner)->post(route('workspaces.members.store', $workspace), [
            'email' => $candidate->email, 'role' => 'admin',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id, 'user_id' => $candidate->id,
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $otherProject->id, 'user_id' => $candidate->id,
        ]);
        $this->assertTrue($candidate->can('view', $project));
    }

    public function test_workspace_administrator_has_inherited_project_access_without_explicit_grant(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $candidate = User::factory()->create();

        $this->actingAs($owner)->post(route('workspaces.members.store', $workspace), [
            'email' => $candidate->email, 'role' => 'admin',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($candidate->can('view', $project));
        $this->assertTrue($candidate->can('manageManagers', $project));
        $this->assertFalse($candidate->projectMemberships()->exists());
    }

    public function test_suspended_admin_loses_inherited_project_access_and_restores_it_on_activation(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $admin = User::factory()->create();
        $membership = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->assertTrue($admin->can('view', $project));
        $this->actingAs($owner)->patch(route('workspaces.members.suspend', [$workspace, $membership]))->assertRedirect();
        $this->assertFalse($admin->can('view', $project));
        $this->assertFalse($admin->can('manageMembers', $workspace));
        $this->patch(route('workspaces.members.restore', [$workspace, $membership]))->assertRedirect();
        $this->assertTrue($admin->can('view', $project));
    }

    public function test_removing_admin_revokes_inherited_access_and_keeps_account(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $admin = User::factory()->create();
        $membership = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $membership]))->assertRedirect();
        $this->assertDatabaseMissing('workspace_members', ['id' => $membership->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertFalse($admin->can('view', $project));
    }

    public function test_admin_cannot_change_other_administrators_and_owner_cannot_remove_self(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create();
        $member = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);
        $ownerMembership = $workspace->memberships()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($admin)->patch(route('workspaces.members.suspend', [$workspace, $ownerMembership]))->assertForbidden();
        $this->delete(route('workspaces.members.destroy', [$workspace, $ownerMembership]))->assertForbidden();
        $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $ownerMembership]))->assertForbidden();
        $this->patch(route('workspaces.members.suspend', [$workspace, $ownerMembership]))->assertForbidden();
        $this->actingAs($admin)->delete(route('workspaces.members.destroy', [$workspace, $member]))->assertForbidden();
    }

    public function test_cross_workspace_member_ids_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $otherOwner = User::factory()->create();
        $otherWorkspace = app(CreateWorkspace::class)->handle($otherOwner, 'Other Team');
        $otherAdmin = WorkspaceMember::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($owner)->patch(route('workspaces.members.suspend', [$workspace, $otherAdmin]))->assertNotFound();
        $this->patch(route('workspaces.members.restore', [$workspace, $otherAdmin]))->assertNotFound();
        $this->delete(route('workspaces.members.destroy', [$workspace, $otherAdmin]))->assertNotFound();
        $this->assertDatabaseHas('workspace_members', ['id' => $otherAdmin->id]);
    }

    public function test_guest_cannot_access_admin_screen_or_add_admin(): void
    {
        $workspace = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Product Team');
        $this->get(route('workspaces.members.index', $workspace))->assertRedirect(route('login'));
        $this->post(route('workspaces.members.store', $workspace), [
            'email' => 'admin@example.test', 'role' => 'admin',
        ])->assertRedirect(route('login'));
    }
}
