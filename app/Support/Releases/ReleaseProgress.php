<?php

namespace App\Support\Releases;

use App\Enums\TaskStatusCategory;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

final class ReleaseProgress
{
    /**
     * Progress always covers every assigned task, not just the current page.
     * A task is completed only when it is in the Done category and has a completion timestamp.
     *
     * @return array{total:int, completed:int, todo:int, in_progress:int, done:int, canceled:int, percentage:int}
     */
    public function forRelease(Release $release): array
    {
        $row = DB::table('tasks')
            ->join('task_statuses', 'task_statuses.id', '=', 'tasks.status_id')
            ->where('tasks.release_id', $release->id)
            ->where('tasks.project_id', $release->project_id)
            ->selectRaw(<<<'SQL'
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN task_statuses.category = ? THEN 1 ELSE 0 END), 0) AS todo,
                COALESCE(SUM(CASE WHEN task_statuses.category = ? THEN 1 ELSE 0 END), 0) AS in_progress,
                COALESCE(SUM(CASE WHEN task_statuses.category = ? THEN 1 ELSE 0 END), 0) AS done,
                COALESCE(SUM(CASE WHEN task_statuses.category = ? THEN 1 ELSE 0 END), 0) AS canceled,
                COALESCE(SUM(CASE WHEN task_statuses.category = ? AND tasks.completed_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS completed
                SQL, [
                TaskStatusCategory::TODO->value,
                TaskStatusCategory::IN_PROGRESS->value,
                TaskStatusCategory::DONE->value,
                TaskStatusCategory::CANCELED->value,
                TaskStatusCategory::DONE->value,
            ])
            ->first();

        $total = (int) ($row?->total ?? 0);
        $completed = (int) ($row?->completed ?? 0);

        return [
            'total' => $total,
            'completed' => $completed,
            'todo' => (int) ($row?->todo ?? 0),
            'in_progress' => (int) ($row?->in_progress ?? 0),
            'done' => (int) ($row?->done ?? 0),
            'canceled' => (int) ($row?->canceled ?? 0),
            'percentage' => $total === 0 ? 0 : (int) round($completed * 100 / $total),
        ];
    }

    /**
     * Return a small, actionable list of tasks preventing publication.
     * Uses the same completion rule as the progress counter.
     *
     * @return list<array{id:int,reference:string,title:string,status:string,missing_completion_date:bool}>
     */
    public function blockingTasks(Release $release, int $limit = 5): array
    {
        $tasks = DB::table('tasks')
            ->join('task_statuses', 'task_statuses.id', '=', 'tasks.status_id')
            ->where('tasks.project_id', $release->project_id)
            ->where('tasks.release_id', $release->id)
            ->where(function ($query): void {
                $query->where('task_statuses.category', '!=', TaskStatusCategory::DONE->value)
                    ->orWhereNull('tasks.completed_at');
            })
            ->orderBy('tasks.number')
            ->limit($limit)
            ->get(['tasks.id', 'tasks.number', 'tasks.title', 'tasks.completed_at', 'task_statuses.category', 'task_statuses.name AS status_name']);

        $projectKey = $release->project->key;

        return $tasks->map(static fn ($task): array => [
            'id' => (int) $task->id,
            'reference' => $projectKey.'-'.$task->number,
            'title' => $task->title,
            'status' => $task->status_name,
            'missing_completion_date' => (int) $task->category === TaskStatusCategory::DONE->value
                && $task->completed_at === null,
        ])->all();
    }
}
