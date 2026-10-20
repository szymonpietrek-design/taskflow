<?php

namespace App\Support\Tasks;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

/** Shared form options for creating a task from a project or the task list. */
class TaskCreationOptions
{
    /** @return array{statuses: array, assignees: array, parent_tasks: array} */
    public function forProject(Project $project): array
    {
        return [
            'statuses' => $project->taskStatuses()
                ->orderBy('position')
                ->get()
                ->map(fn (TaskStatus $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'category' => strtolower($status->category->name),
                ])->all(),
            // Keep this list project-scoped; do not expose all workspace users.
            'assignees' => User::query()
                ->whereNull('disabled_at')
                ->where(function ($query) use ($project): void {
                    $query->whereHas('workspaceMemberships', function ($memberships) use ($project): void {
                        $memberships->where('workspace_id', $project->workspace_id)
                            ->whereNull('suspended_at')
                            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]);
                    })->orWhereHas('projectMemberships', fn ($memberships) => $memberships->where('project_id', $project->id));
                })
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            'parent_tasks' => $project->tasks()
                ->whereNull('parent_id')
                ->orderByDesc('number')
                ->get(['id', 'number', 'title'])
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'reference' => $project->key.'-'.$task->number,
                    'title' => $task->title,
                ])->all(),
        ];
    }
}
