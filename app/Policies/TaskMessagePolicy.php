<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TaskMessagePolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return Gate::forUser($user)->allows('view', $task);
    }

    public function create(User $user, Task $task): bool
    {
        return Gate::forUser($user)->allows('update', $task);
    }

    public function update(User $user, TaskMessage $message): bool
    {
        return $message->user_id === $user->id
            && Gate::forUser($user)->allows('update', $message->task);
    }

    public function delete(User $user, TaskMessage $message): bool
    {
        return $this->update($user, $message);
    }
}
