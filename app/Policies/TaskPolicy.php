<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function __construct(private readonly ProjectPolicy $projects) {}

    public function viewAny(User $user, Project $project): bool
    {
        return $this->projects->view($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->projects->view($user, $task->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $project->archived_at === null && $this->projects->view($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->create($user, $task->project()->firstOrFail());
    }

    public function delete(User $user, Task $task): bool
    {
        $project = $task->project()->firstOrFail();
        return $project->archived_at === null && $this->projects->manageMembers($user, $project);
    }
}
