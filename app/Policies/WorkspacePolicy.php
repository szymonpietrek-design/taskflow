<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array(
            $this->membership($user, $workspace)?->role,
            [WorkspaceRole::OWNER, WorkspaceRole::ADMIN],
            true,
        );
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace)?->role === WorkspaceRole::OWNER;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return in_array(
            $this->membership($user, $workspace)?->role,
            [WorkspaceRole::OWNER, WorkspaceRole::ADMIN],
            true,
        );
    }

    private function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return $user->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->first();
    }
}
