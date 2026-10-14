<?php

namespace App\Actions\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreateTask
{
    public function __construct(
        private readonly EnsureDefaultTaskStatuses $ensureDefaultTaskStatuses,
    ) {}

    public function handle(
        User $creator,
        Project $project,
        string $title,
        TaskType $type = TaskType::TASK,
        TaskPriority $priority = TaskPriority::NORMAL,
        ?TaskStatus $status = null,
        ?User $assignee = null,
        ?Task $parent = null,
        ?string $description = null,
        ?DateTimeInterface $dueDate = null,
    ): Task {
        Gate::forUser($creator)->authorize('create', [Task::class, $project]);

        $title = trim($title);
        $description = $description !== null ? trim($description) : null;

        if ($title === '') {
            throw new InvalidArgumentException(__('Task title cannot be empty.'));
        }

        if ($status !== null && $status->project_id !== $project->id) {
            throw new InvalidArgumentException(__('Task status must belong to the same project.'));
        }

        if ($parent !== null) {
            if ($parent->project_id !== $project->id) {
                throw new InvalidArgumentException(__('Parent task must belong to the same project.'));
            }

            if ($parent->parent_id !== null) {
                throw new InvalidArgumentException(__('Tasks can only have one level of subtasks.'));
            }
        }

        if ($assignee !== null && Gate::forUser($assignee)->denies('create', [Task::class, $project])) {
            throw new InvalidArgumentException(__('Assignee must be a project contributor.'));
        }

        return DB::transaction(function () use (
            $creator,
            $project,
            $title,
            $type,
            $priority,
            $status,
            $assignee,
            $parent,
            $description,
            $dueDate,
        ): Task {
            /** @var Project $lockedProject */
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statuses = $this->ensureDefaultTaskStatuses->handle($lockedProject);

            $taskStatus = $status ?? $statuses->first(
                fn (TaskStatus $candidate): bool => $candidate->category === TaskStatusCategory::TODO,
            );

            if (! $taskStatus instanceof TaskStatus) {
                throw new InvalidArgumentException(__('Project must have a Todo task status.'));
            }

            $number = $lockedProject->task_sequence + 1;

            $lockedProject->forceFill([
                'task_sequence' => $number,
            ])->save();

            $position = ((int) $lockedProject->tasks()
                ->where('status_id', $taskStatus->id)
                ->max('position')) + 1000;

            $task = $lockedProject->tasks()->create([
                'parent_id' => $parent?->id,
                'number' => $number,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'type' => $type,
                'priority' => $priority,
                'status_id' => $taskStatus->id,
                'assignee_id' => $assignee?->id,
                'created_by' => $creator->id,
                'position' => $position,
                'due_date' => $dueDate,
                'completed_at' => $taskStatus->category === TaskStatusCategory::DONE ? now() : null,
            ]);

            return $task->load(['project', 'status', 'assignee', 'creator', 'parent']);
        });
    }
}
