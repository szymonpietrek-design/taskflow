<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskWorklog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TaskWorklogPolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return Gate::forUser($user)->allows('view', $task);
    }

    public function create(User $user, Task $task): bool
    {
        return $task->project->archived_at === null
            && Gate::forUser($user)->allows('update', $task);
    }

    public function update(User $user, TaskWorklog $worklog): bool
    {
        return $worklog->user_id === $user->id
            && $this->create($user, $worklog->task);
    }

    public function delete(User $user, TaskWorklog $worklog): bool
    {
        return $this->update($user, $worklog);
    }
}
