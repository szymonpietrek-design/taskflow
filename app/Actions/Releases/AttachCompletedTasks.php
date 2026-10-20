<?php

namespace App\Actions\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskStatusCategory;
use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class AttachCompletedTasks
{
    public function __construct(
        private readonly SetTaskRelease $setTaskRelease,
    ) {}

    /**
     * Attach currently unassigned, completed tasks to one editable release.
     * Uses SetTaskRelease so each change is authorized and recorded in Activity.
     * All changes are atomic; subsequent runs skip already assigned tasks.
     */
    public function handle(User $actor, Release $release): int
    {
        $project = $release->project;
        Gate::forUser($actor)->authorize('update', $project);

        return DB::transaction(function () use ($actor, $project, $release): int {
            $release->refresh();

            if ($project->type !== ProjectType::APPLICATION || $project->archived_at !== null) {
                throw new InvalidArgumentException(__('Completed tasks can only be attached in active application projects.'));
            }

            if (! in_array($release->status, [ReleaseStatus::DRAFT, ReleaseStatus::PLANNED], true)) {
                throw new InvalidArgumentException(__('Tasks in released or canceled versions cannot be changed.'));
            }

            $attached = 0;
            Task::query()
                ->where('project_id', $project->id)
                ->whereNull('release_id')
                ->whereNotNull('completed_at')
                ->whereHas('status', fn (Builder $query) => $query
                    ->where('project_id', $project->id)
                    ->where('category', TaskStatusCategory::DONE->value))
                ->orderBy('id')
                ->chunkById(100, function ($tasks) use ($actor, $release, &$attached): void {
                    foreach ($tasks as $task) {
                        $this->setTaskRelease->handle($actor, $task, $release);
                        $attached++;
                    }
                });

            return $attached;
        });
    }
}
