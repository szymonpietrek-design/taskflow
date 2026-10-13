<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        $workspaceMembership = $this->workspaceMembership($user, $project);

        if ($workspaceMembership === null) {
            return false;
        }

        if ($this->isWorkspaceAdministrator($workspaceMembership)) {
            return true;
        }

        if ($project->visibility === ProjectVisibility::WORKSPACE) {
            return true;
        }

        return $this->projectMembership($user, $project) !== null;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        $membership = $user->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')
            ->first();

        return $membership !== null && $this->isWorkspaceAdministrator($membership);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canManageProject($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canManageProject($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->canManageProject($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->canManageProject($user, $project);
    }

    private function canManageProject(User $user, Project $project): bool
    {
        $workspaceMembership = $this->workspaceMembership($user, $project);

        if ($workspaceMembership === null) {
            return false;
        }

        if ($this->isWorkspaceAdministrator($workspaceMembership)) {
            return true;
        }

        return $this->projectMembership($user, $project)?->role === ProjectRole::MANAGER;
    }

    private function isWorkspaceAdministrator(WorkspaceMember $membership): bool
    {
        return in_array(
            $membership->role,
            [WorkspaceRole::OWNER, WorkspaceRole::ADMIN],
            true,
        );
    }

    private function workspaceMembership(User $user, Project $project): ?WorkspaceMember
    {
        return $user->workspaceMemberships()
            ->where('workspace_id', $project->workspace_id)
            ->whereNull('suspended_at')
            ->first();
    }

    private function projectMembership(User $user, Project $project): ?ProjectMember
    {
        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first();
    }
}
