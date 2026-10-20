<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

class ProjectPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Project $project): bool
    {
        if ($this->workspaceAdmin($user, $project->workspace_id)) {
            return true;
        }

        // Suspending workspace administration must revoke access even if an
        // old explicit project grant survived from earlier data.
        if ($user->workspaceMemberships()
            ->where('workspace_id', $project->workspace_id)
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->whereNotNull('suspended_at')
            ->exists()) {
            return false;
        }

        // Archived projects remain readable by their managers for restoration,
        // but regular members must not bypass the archived-project list by URL.
        $membership = $user->projectMemberships()->where('project_id', $project->id);

        if ($project->archived_at !== null) {
            $membership->where('role', ProjectRole::MANAGER->value);
        }

        return $membership->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->workspaceAdmin($user, $workspace->id);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->archived_at === null && $this->canManageProject($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->canManageProject($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $project->archived_at === null && $this->canManageProject($user, $project);
    }

    public function manageManagers(User $user, Project $project): bool
    {
        return $project->archived_at === null && $this->workspaceAdmin($user, $project->workspace_id);
    }

    private function canManageProject(User $user, Project $project): bool
    {
        return $this->workspaceAdmin($user, $project->workspace_id)
            || $user->projectMemberships()
                ->where('project_id', $project->id)
                ->where('role', ProjectRole::MANAGER->value)->exists();
    }

    private function workspaceAdmin(User $user, int $workspaceId): bool
    {
        return $user->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->whereNull('suspended_at')
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->exists();
    }
}
