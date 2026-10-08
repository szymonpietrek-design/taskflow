<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatusCategory;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ChangeTaskStatus
{
    public function handle(User $actor, Task $task, TaskStatus $status): Task
    {
        Gate::forUser($actor)->authorize('update', $task);

        if ($status->project_id !== $task->project_id) {
            throw new InvalidArgumentException('Task status must belong to the same project.');
        }

        return DB::transaction(function () use ($actor, $task, $status): Task {
            /** @var Task $lockedTask */
            $lockedTask = Task::query()
                ->with('status')
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTask->status_id === $status->id) {
                return $lockedTask->load(['project', 'status', 'assignee', 'creator', 'parent']);
            }

            $previousStatus = $lockedTask->status;

            $lockedTask->forceFill([
                'status_id' => $status->id,
                'completed_at' => $status->category === TaskStatusCategory::DONE
                    ? ($lockedTask->completed_at ?? now())
                    : null,
            ])->save();

            $lockedTask->activities()->create([
                'user_id' => $actor->id,
                'type' => TaskActivityType::STATUS_CHANGED,
                'data' => [
                    'from' => [
                        'id' => $previousStatus->id,
                        'name' => $previousStatus->name,
                        'category' => $previousStatus->category->value,
                    ],
                    'to' => [
                        'id' => $status->id,
                        'name' => $status->name,
                        'category' => $status->category->value,
                    ],
                ],
            ]);

            return $lockedTask->load(['project', 'status', 'assignee', 'creator', 'parent']);
        });
    }
}
