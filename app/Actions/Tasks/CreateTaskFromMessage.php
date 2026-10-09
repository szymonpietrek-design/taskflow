<?php

namespace App\Actions\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreateTaskFromMessage
{
    public function __construct(private readonly CreateTask $createTask) {}

    public function handle(
        User $creator,
        Project $project,
        Task $sourceTask,
        TaskMessage $sourceMessage,
        string $title,
        TaskType $type = TaskType::TASK,
        TaskPriority $priority = TaskPriority::NORMAL,
        ?TaskStatus $status = null,
        ?User $assignee = null,
        ?string $description = null,
        ?DateTimeInterface $dueDate = null,
    ): Task {
        if ($sourceTask->project_id !== $project->id || $sourceMessage->task_id !== $sourceTask->id) {
            throw new InvalidArgumentException('Source message must belong to the selected project and task.');
        }

        Gate::forUser($creator)->authorize('view', $sourceTask);
        Gate::forUser($creator)->authorize('create', [Task::class, $project]);

        return DB::transaction(function () use (
            $creator, $project, $sourceTask, $sourceMessage, $title,
            $type, $priority, $status, $assignee, $description, $dueDate,
        ): Task {
            // Lock the source while creating the task so deletion cannot race the source link.
            $currentMessage = TaskMessage::query()->whereKey($sourceMessage->id)->lockForUpdate()->firstOrFail();

            if ($currentMessage->task_id !== $sourceTask->id) {
                throw new InvalidArgumentException('Source message was moved to another task.');
            }

            $task = $this->createTask->handle(
                $creator, $project, $title, $type, $priority, $status, $assignee,
                parent: null, description: $description, dueDate: $dueDate,
            );

            $task->forceFill([
                'source_task_id' => $sourceTask->id,
                'source_message_id' => $currentMessage->id,
            ])->save();

            return $task->load(['sourceTask', 'sourceMessage']);
        });
    }
}
