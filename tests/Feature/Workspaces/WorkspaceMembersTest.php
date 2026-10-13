<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_members_of_the_requested_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $member = User::factory()->create(['name' => 'Anna Example', 'email' => 'anna@example.test']);
        $outside = User::factory()->create(['name' => 'Outside Person', 'email' => 'outside@example.test']);
        app(CreateWorkspace::class)->handle($outside, 'Other Team');

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)
            ->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Members')
                ->where('workspaceItem.id', $workspace->id)
                ->where('workspaceItem.name', 'Product Team')
                ->where('members.total', 2)
                ->has('members.data', 2)
                ->where('members.data.0.name', $owner->name)
                ->where('members.data.0.role', 'owner')
                ->where('members.data.0.is_you', true)
                ->where('members.data.1.name', 'Anna Example')
                ->where('members.data.1.email', 'anna@example.test')
                ->where('members.data.1.role', 'member')
                ->etc());

    }

    public function test_admin_can_view_members_of_their_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($admin)
            ->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Members')
                ->where('members.total', 2)
                ->where('members.data.1.role', 'admin')
                ->where('members.data.1.is_you', true)
                ->etc());
    }

    public function test_regular_member_cannot_access_the_members_list(): void
    {
        $workspace = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Product Team');
        $member = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($member)
            ->get(route('workspaces.members.index', $workspace))
            ->assertForbidden();
    }

    public function test_users_outside_workspace_cannot_access_members_list(): void
    {
        $workspace = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Private Team');

        $this->actingAs(User::factory()->create())
            ->get(route('workspaces.members.index', $workspace))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $workspace = Workspace::factory()->create();

        $this->get(route('workspaces.members.index', $workspace))
            ->assertRedirect(route('login'));
    }

    public function test_search_filters_members_and_preserves_workspace_isolation(): void
    {
        $owner = User::factory()->create(['name' => 'Current Owner', 'email' => 'owner@example.test']);
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $matched = User::factory()->create(['name' => 'Jan Kowalski', 'email' => 'jan@example.test']);
        $otherUser = User::factory()->create(['name' => 'Jan Outside', 'email' => 'other@example.test']);
        app(CreateWorkspace::class)->handle($otherUser, 'Private Team');

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $matched->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)
            ->get(route('workspaces.members.index', ['workspace' => $workspace, 'q' => 'jan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Members')
                ->where('filters.q', 'jan')
                ->where('members.total', 1)
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Jan Kowalski')
                ->where('members.data.0.email', 'jan@example.test')
                ->etc());
    }

    public function test_members_list_is_paginated(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Big Team');

        foreach (range(1, 22) as $i) {
            WorkspaceMember::factory()->create([
                'workspace_id' => $workspace->id,
                'user_id' => User::factory()->create()->id,
                'role' => WorkspaceRole::MEMBER,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Members')
                ->where('members.total', 23)
                ->has('members.data', 20)
                ->where('members.current_page', 1)
                ->where('members.last_page', 2)
                ->etc());
    }

    public function test_owner_can_add_an_existing_user_as_member(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $person = User::factory()->create(['email' => 'person@example.test']);

        $this->actingAs($owner)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => '  PERSON@EXAMPLE.TEST ',
                'role' => 'member',
            ])->assertRedirect();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
            'role' => WorkspaceRole::MEMBER->value,
        ]);
        $this->assertNotNull(WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $person->id)
            ->first()?->joined_at);
    }

    public function test_owner_can_appoint_an_admin(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $person = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => $person->email,
                'role' => 'admin',
            ])->assertRedirect();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
            'role' => WorkspaceRole::ADMIN->value,
        ]);
    }

    public function test_workspace_admin_can_add_members_but_cannot_appoint_other_admins(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
        ]);
        $regular = User::factory()->create();
        $promoted = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => $regular->email,
                'role' => 'member',
            ])->assertRedirect();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $regular->id,
            'role' => WorkspaceRole::MEMBER->value,
        ]);

        $this->actingAs($admin)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => $promoted->email,
                'role' => 'admin',
            ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $promoted->id,
        ]);
    }

    public function test_duplicate_membership_is_rejected_and_role_is_not_overwritten(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $person = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => $person->email,
                'role' => 'admin',
            ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
            'role' => WorkspaceRole::MEMBER->value,
        ]);
        $this->assertSame(1, WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $person->id)
            ->count());
    }

    public function test_missing_account_and_invalid_input_do_not_create_memberships(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');

        $this->actingAs($owner)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => 'missing@example.test',
                'role' => 'member',
            ])->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->post(route('workspaces.members.store', $workspace), [
                'email' => 'not-an-email',
                'role' => 'owner',
            ])->assertSessionHasErrors(['email', 'role']);

        $this->assertSame(1, $workspace->memberships()->count());
    }

    public function test_regular_member_and_outsider_cannot_add_users(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $person = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        foreach ([$member, $outsider] as $actor) {
            $this->actingAs($actor)
                ->post(route('workspaces.members.store', $workspace), [
                    'email' => $person->email,
                    'role' => 'member',
                ])->assertForbidden();
        }

        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
        ]);
    }

    public function test_guest_cannot_add_a_member(): void
    {
        $workspace = Workspace::factory()->create();

        $this->post(route('workspaces.members.store', $workspace), [
            'email' => 'example@example.test',
            'role' => 'member',
        ])->assertRedirect(route('login'));
    }

    public function test_owner_add_member_screen_exposes_stable_user_id_and_role_capability(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');

        $this->actingAs($owner)
            ->get(route('workspaces.members.index', $workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Members')
                ->where('can_assign_admin', true)
                ->where('members.data.0.user_id', $owner->id)
                ->etc());
    }

    public function test_owner_can_suspend_and_restore_member_without_losing_project_assignments(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $person = User::factory()->create();
        $member = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'visibility' => ProjectVisibility::WORKSPACE,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $person->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $task = Task::factory()->create(['project_id' => $project->id]);
        $this->assertTrue($person->can('view', $project));
        $this->assertTrue($person->can('delete', $task));
        $this->actingAs($owner)->patch(route('workspaces.members.suspend', [$workspace, $member]))->assertRedirect();

        $this->assertNotNull($member->fresh()->suspended_at);
        $this->assertFalse($person->can('view', $workspace));
        $this->assertFalse($person->can('view', $project));
        $this->assertFalse($person->can('update', $project));
        $this->assertFalse($person->can('delete', $task));
        $this->assertFalse($person->workspaces()->whereKey($workspace->id)->exists());
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $person->id]);
        $this->actingAs($person)->get(route('projects.show', $project))->assertForbidden();

        $this->actingAs($owner)->patch(route('workspaces.members.restore', [$workspace, $member]))->assertRedirect();
        $this->assertNull($member->fresh()->suspended_at);
        $this->assertTrue($person->can('view', $project));
        $this->assertTrue($person->can('update', $project));
        $this->assertTrue($person->can('delete', $task));
    }

    public function test_owner_removing_member_revokes_project_permissions_but_retains_account(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $person = User::factory()->create();
        $member = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $person->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $person->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $member]))->assertRedirect();
        $this->assertDatabaseMissing('workspace_members', ['id' => $member->id]);
        $this->assertDatabaseMissing('project_members', ['project_id' => $project->id, 'user_id' => $person->id]);
        $this->assertDatabaseHas('users', ['id' => $person->id]);
        $this->assertFalse($person->can('view', $project));
        $this->assertFalse($person->can('update', $project));
        $this->actingAs($person)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_admin_may_manage_regular_members_but_cannot_manage_admins_or_owner(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create();
        $adminMember = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $admin->id, 'role' => WorkspaceRole::ADMIN,
        ]);
        $member = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => User::factory()->create()->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $ownerMember = $workspace->memberships()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($admin)->patch(route('workspaces.members.suspend', [$workspace, $member]))->assertRedirect();
        $this->assertNotNull($member->fresh()->suspended_at);

        $this->actingAs($admin)->patch(route('workspaces.members.suspend', [$workspace, $ownerMember]))->assertForbidden();
        $this->actingAs($admin)->delete(route('workspaces.members.destroy', [$workspace, $ownerMember]))->assertForbidden();
        $this->actingAs($admin)->patch(route('workspaces.members.suspend', [$workspace, $adminMember]))->assertForbidden();
        $this->actingAs($admin)->delete(route('workspaces.members.destroy', [$workspace, $adminMember]))->assertForbidden();
        $this->actingAs($owner)->patch(route('workspaces.members.suspend', [$workspace, $ownerMember]))->assertForbidden();
        $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $ownerMember]))->assertForbidden();
    }

    public function test_only_owner_may_suspend_admin_and_admin_cannot_manage_other_admins(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $admin = User::factory()->create();
        $membership = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $admin->id, 'role' => WorkspaceRole::ADMIN,
        ]);
        $second = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $second->id, 'role' => WorkspaceRole::ADMIN,
        ]);

        $this->actingAs($second)->patch(route('workspaces.members.suspend', [$workspace, $membership]))->assertForbidden();
        $this->actingAs($owner)->patch(route('workspaces.members.suspend', [$workspace, $membership]))->assertRedirect();
        $this->assertFalse($admin->can('manageMembers', $workspace));
        $this->actingAs($admin)->get(route('workspaces.members.index', $workspace))->assertForbidden();
        $this->actingAs($owner)->patch(route('workspaces.members.restore', [$workspace, $membership]))->assertRedirect();
        $this->assertTrue($admin->can('manageMembers', $workspace));
    }

    public function test_member_actions_reject_cross_workspace_ids_and_unprivileged_users(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $anotherOwner = User::factory()->create();
        $otherWorkspace = app(CreateWorkspace::class)->handle($anotherOwner, 'Other Team');
        $other = WorkspaceMember::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $local = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => User::factory()->create()->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $other]))->assertNotFound();
        $this->actingAs($owner)->patch(route('workspaces.members.suspend', [$workspace, $other]))->assertNotFound();
        $this->actingAs($owner)->patch(route('workspaces.members.restore', [$workspace, $other]))->assertNotFound();

        $memberUser = $local->user;
        $this->actingAs($memberUser)->delete(route('workspaces.members.destroy', [$workspace, $local]))->assertForbidden();
        $this->actingAs($memberUser)->patch(route('workspaces.members.suspend', [$workspace, $local]))->assertForbidden();
        $this->assertDatabaseHas('workspace_members', ['id' => $local->id]);
    }

    public function test_suspended_members_stay_visible_to_owner_and_cannot_be_added_again(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Product Team');
        $person = User::factory()->create();
        $member = WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $person->id, 'role' => WorkspaceRole::MEMBER,
            'suspended_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('workspaces.members.index', $workspace))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/Members')
            ->where('members.data.1.is_suspended', true)
            ->where('members.data.1.can_manage', true)
            ->etc());
        $this->actingAs($owner)->post(route('workspaces.members.store', $workspace), [
            'email' => $person->email, 'role' => 'member',
        ])->assertSessionHasErrors('email');
        $this->assertSame(1, WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)->where('user_id', $person->id)->count());
        $this->assertNotNull($member->fresh()->suspended_at);
    }
}
