<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;

class TaskPolicy
{
    public function __construct(
        private readonly ProjectPolicy $projects,
    ) {}

    public function viewAny(User $user, Project $project): bool
    {
        return $this->canViewProject($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canViewProject($user, $task->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canContribute($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canContribute($user, $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        $project = $task->project;
        $workspaceMembership = $this->workspaceMembership($user, $project);

        if ($workspaceMembership !== null && $this->isWorkspaceAdministrator($workspaceMembership)) {
            return true;
        }

        return $this->projectMembership($user, $project)?->role === ProjectRole::MANAGER;
    }

    private function canViewProject(User $user, Project $project): bool
    {
        return $this->projects->view($user, $project);
    }

    private function canContribute(User $user, Project $project): bool
    {
        $workspaceMembership = $this->workspaceMembership($user, $project);

        if ($workspaceMembership === null) {
            return false;
        }

        if ($this->isWorkspaceAdministrator($workspaceMembership)) {
            return true;
        }

        return $this->projectMembership($user, $project) !== null;
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
            ->first();
    }

    private function projectMembership(User $user, Project $project): ?ProjectMember
    {
        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first();
    }
}
