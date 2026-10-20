<?php

namespace App\Actions\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SetTaskRelease
{
    public function handle(User $actor, Task $task, ?Release $release): Task
    {
        // Managing a release is a project-management operation, even when
        // regular project members are allowed to edit task details.
        Gate::forUser($actor)->authorize('update', $task->project);
        Gate::forUser($actor)->authorize('update', $task);

        if ($task->project->type !== ProjectType::APPLICATION || $task->project->archived_at !== null) {
            throw new InvalidArgumentException(__('Releases can only be edited in active application projects.'));
        }

        if ($release !== null && $release->project_id !== $task->project_id) {
            throw new InvalidArgumentException(__('Release belongs to a different project.'));
        }

        return DB::transaction(function () use ($actor, $task, $release): Task {
            /** @var Task $lockedTask */
            $lockedTask = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $currentId = $lockedTask->release_id;
            $targetId = $release?->id;

            if ($currentId === $targetId) {
                return $lockedTask;
            }

            $previous = $currentId === null ? null : Release::query()->lockForUpdate()->findOrFail($currentId);
            $target = $targetId === null ? null : Release::query()->lockForUpdate()->findOrFail($targetId);

            if (($previous !== null && ! $this->editable($previous)) || ($target !== null && ! $this->editable($target))) {
                throw new InvalidArgumentException(__('Tasks in released or canceled versions cannot be changed.'));
            }

            if ($previous !== null && $target !== null) {
                throw new InvalidArgumentException(__('Remove the task from its current release before adding it to another.'));
            }

            if ($target !== null && $target->project_id !== $lockedTask->project_id) {
                throw new InvalidArgumentException(__('Release belongs to a different project.'));
            }

            $lockedTask->forceFill(['release_id' => $targetId])->save();
            $lockedTask->activities()->create([
                'user_id' => $actor->id,
                'type' => TaskActivityType::RELEASE_CHANGED,
                'data' => [
                    'from' => $this->snapshot($previous),
                    'to' => $this->snapshot($target),
                ],
            ]);

            return $lockedTask->load('release');
        });
    }

    private function editable(Release $release): bool
    {
        return in_array($release->status, [ReleaseStatus::DRAFT, ReleaseStatus::PLANNED], true);
    }

    /** @return array{id:int,version:string}|null */
    private function snapshot(?Release $release): ?array
    {
        return $release === null ? null : ['id' => $release->id, 'version' => $release->version];
    }
}
