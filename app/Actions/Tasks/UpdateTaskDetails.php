<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateTaskDetails
{
    public function handle(
        User $actor,
        Task $task,
        string $title,
        ?string $description,
        TaskPriority $priority,
        ?User $assignee,
        ?DateTimeInterface $dueDate,
    ): Task {
        Gate::forUser($actor)->authorize('update', $task);

        $title = trim($title);
        $description = $description !== null ? trim($description) : null;
        $description = $description === '' ? null : $description;

        if ($title === '') {
            throw new InvalidArgumentException(__('Task title cannot be empty.'));
        }

        return DB::transaction(function () use ($actor, $task, $title, $description, $priority, $assignee, $dueDate): Task {
            /** @var Task $lockedTask */
            $lockedTask = Task::query()
                ->with(['project', 'assignee'])
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verify the new assignee against the same contributor rules as CreateTask.
            if ($assignee?->id !== $lockedTask->assignee_id
                && $assignee !== null
                && Gate::forUser($assignee)->denies('create', [Task::class, $lockedTask->project])) {
                throw new InvalidArgumentException(__('Assignee must be a project contributor.'));
            }

            $changes = [];
            $activities = [];

            if ($lockedTask->title !== $title) {
                $changes['title'] = $title;
                $activities[] = [TaskActivityType::TITLE_CHANGED, $lockedTask->title, $title];
            }

            if ($lockedTask->description !== $description) {
                $changes['description'] = $description;
                $activities[] = [TaskActivityType::DESCRIPTION_CHANGED, $lockedTask->description, $description];
            }

            if ($lockedTask->priority !== $priority) {
                $changes['priority'] = $priority;
                $activities[] = [
                    TaskActivityType::PRIORITY_CHANGED,
                    ['value' => $lockedTask->priority->value, 'name' => $lockedTask->priority->name],
                    ['value' => $priority->value, 'name' => $priority->name],
                ];
            }

            if ($lockedTask->assignee_id !== $assignee?->id) {
                $changes['assignee_id'] = $assignee?->id;
                $activities[] = [
                    TaskActivityType::ASSIGNEE_CHANGED,
                    $lockedTask->assignee === null ? null : [
                        'id' => $lockedTask->assignee->id,
                        'name' => $lockedTask->assignee->name,
                    ],
                    $assignee === null ? null : ['id' => $assignee->id, 'name' => $assignee->name],
                ];
            }

            $oldDueDate = $lockedTask->due_date?->toDateString();
            $newDueDate = $dueDate?->format('Y-m-d');

            if ($oldDueDate !== $newDueDate) {
                $changes['due_date'] = $newDueDate;
                $activities[] = [TaskActivityType::DUE_DATE_CHANGED, $oldDueDate, $newDueDate];
            }

            if ($changes === []) {
                return $lockedTask->load(['status', 'creator', 'parent']);
            }

            $lockedTask->forceFill($changes)->save();

            foreach ($activities as [$type, $from, $to]) {
                $lockedTask->activities()->create([
                    'user_id' => $actor->id,
                    'type' => $type,
                    'data' => ['from' => $from, 'to' => $to],
                ]);
            }

            return $lockedTask->load(['status', 'assignee', 'creator', 'parent']);
        });
    }
}
