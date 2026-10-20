<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_workspace_member_role_alone_cannot_view_workspace(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::MEMBER);

        $this->assertFalse($user->can('view', $workspace));
        $this->assertFalse($user->can('update', $workspace));
        $this->assertFalse($user->can('delete', $workspace));
        $this->assertFalse($user->can('manageMembers', $workspace));
    }

    public function test_workspace_admins_can_update_and_manage_members_but_cannot_delete_the_workspace(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::ADMIN);

        $this->assertTrue($user->can('view', $workspace));
        $this->assertTrue($user->can('update', $workspace));
        $this->assertTrue($user->can('manageMembers', $workspace));
        $this->assertFalse($user->can('delete', $workspace));
    }

    public function test_workspace_owners_have_full_workspace_permissions(): void
    {
        [$workspace, $user] = $this->workspaceWithMember(WorkspaceRole::OWNER);

        $this->assertTrue($user->can('view', $workspace));
        $this->assertTrue($user->can('update', $workspace));
        $this->assertTrue($user->can('delete', $workspace));
        $this->assertTrue($user->can('manageMembers', $workspace));
    }

    public function test_non_members_cannot_access_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($user->can('view', $workspace));
        $this->assertFalse($user->can('update', $workspace));
        $this->assertFalse($user->can('delete', $workspace));
        $this->assertFalse($user->can('manageMembers', $workspace));
    }

    /**
     * @return array{Workspace, User}
     */
    private function workspaceWithMember(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return [$workspace, $user];
    }
}
