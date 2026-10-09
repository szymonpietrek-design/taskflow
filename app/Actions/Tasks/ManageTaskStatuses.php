<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ManageTaskStatuses
{
    public function create(User $actor, Project $project, string $name, TaskStatusCategory $category): TaskStatus
    {
        Gate::forUser($actor)->authorize('update', $project);
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Status name is required.');
        }

        return DB::transaction(function () use ($project, $name, $category): TaskStatus {
            $this->lockProject($project);

            if (! $project->taskStatuses()->exists() && $category !== TaskStatusCategory::TODO) {
                throw new InvalidArgumentException('The first project status must belong to Todo.');
            }

            $base = Str::slug($name) ?: 'status';
            $slug = $base;
            $suffix = 2;

            while ($project->taskStatuses()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            $position = ((int) $project->taskStatuses()->max('position')) + 1000;

            return $project->taskStatuses()->create([
                'name' => $name,
                'slug' => $slug,
                'category' => $category,
                'position' => $position,
            ]);
        });
    }

    public function update(User $actor, Project $project, TaskStatus $status, string $name, TaskStatusCategory $category): TaskStatus
    {
        Gate::forUser($actor)->authorize('update', $project);
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Status name is required.');
        }

        return DB::transaction(function () use ($project, $status, $name, $category): TaskStatus {
            $this->lockProject($project);
            $current = $this->lockStatus($project, $status);

            if ($current->category !== $category) {
                if ($current->tasks()->exists()) {
                    throw new InvalidArgumentException('Cannot change the category of a status used by tasks.');
                }
                $this->guardLastTodo($project, $current);
            }

            // Keep the slug stable: renaming a status should not change its identifier.
            $current->forceFill(['name' => $name, 'category' => $category])->save();

            return $current->refresh();
        });
    }

    public function move(User $actor, Project $project, TaskStatus $status, string $direction): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Invalid direction.');
        }

        DB::transaction(function () use ($project, $status, $direction): void {
            $this->lockProject($project);
            $this->lockStatus($project, $status);

            $statuses = $project->taskStatuses()->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $ids = $statuses->modelKeys();
            $index = array_search($status->id, $ids, true);
            $swapWith = $index + ($direction === 'up' ? -1 : 1);

            if ($swapWith < 0 || $swapWith >= count($ids)) {
                return;
            }

            [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

            foreach ($ids as $order => $id) {
                $project->taskStatuses()->whereKey($id)->update(['position' => ($order + 1) * 1000]);
            }
        });
    }

    public function delete(User $actor, Project $project, TaskStatus $status): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        DB::transaction(function () use ($project, $status): void {
            $this->lockProject($project);
            $current = $this->lockStatus($project, $status);

            if ($current->tasks()->exists()) {
                throw new InvalidArgumentException('Cannot delete a status used by tasks.');
            }

            $this->guardLastTodo($project, $current);
            $current->delete();
        });
    }

    private function guardLastTodo(Project $project, TaskStatus $status): void
    {
        if ($status->category === TaskStatusCategory::TODO && ! $project->taskStatuses()
            ->where('category', TaskStatusCategory::TODO->value)
            ->where('id', '!=', $status->id)
            ->exists()) {
            throw new InvalidArgumentException('A project must keep at least one Todo status.');
        }
    }

    private function lockProject(Project $project): void
    {
        Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
    }

    private function lockStatus(Project $project, TaskStatus $status): TaskStatus
    {
        return $project->taskStatuses()->whereKey($status->id)->lockForUpdate()->firstOrFail();
    }
}
