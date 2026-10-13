<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Collection;

final class EnsureDefaultTaskStatuses
{
    /**
     * Ensure a project has the default workflow when it has no statuses yet.
     *
     * @return Collection<int, TaskStatus>
     */
    public function handle(Project $project): Collection
    {
        if (! $project->taskStatuses()->exists()) {
            foreach ($this->defaults() as $status) {
                $project->taskStatuses()->create($status);
            }
        }

        return $project->taskStatuses()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array{name: string, slug: string, category: TaskStatusCategory, position: int}>
     */
    private function defaults(): array
    {
        return [
            [
                'name' => 'Todo',
                'slug' => 'todo',
                'category' => TaskStatusCategory::TODO,
                'position' => 1000,
            ],
            [
                'name' => 'In Progress',
                'slug' => 'in-progress',
                'category' => TaskStatusCategory::IN_PROGRESS,
                'position' => 2000,
            ],
            [
                'name' => 'Done',
                'slug' => 'done',
                'category' => TaskStatusCategory::DONE,
                'position' => 3000,
            ],
            [
                'name' => 'Canceled',
                'slug' => 'canceled',
                'category' => TaskStatusCategory::CANCELED,
                'position' => 4000,
            ],
        ];
    }
}
