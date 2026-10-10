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
}
