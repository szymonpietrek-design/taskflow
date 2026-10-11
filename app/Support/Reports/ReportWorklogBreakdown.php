<?php

namespace App\Support\Reports;

use App\Models\Report;
use App\Models\TaskWorklog;
use Illuminate\Database\Eloquent\Builder;

final class ReportWorklogBreakdown
{
    /**
     * @return array{
     *     entries:int,
     *     total_minutes:int,
     *     creative_entries:int,
     *     creative_minutes:int,
     *     by_user:list<array{user_id:int|null,name:string,entries:int,total_minutes:int,creative_minutes:int}>,
     *     by_task:list<array{task_id:int,number:int,title:string,entries:int,total_minutes:int,creative_minutes:int}>
     * }
     */
    public function forReport(Report $report): array
    {
        $totals = $this->query($report)
            ->selectRaw('COUNT(*) AS entries, COALESCE(SUM(task_worklogs.duration_minutes), 0) AS total_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN task_worklogs.is_creative = 1 THEN 1 ELSE 0 END), 0) AS creative_entries')
            ->selectRaw('COALESCE(SUM(CASE WHEN task_worklogs.is_creative = 1 THEN task_worklogs.duration_minutes ELSE 0 END), 0) AS creative_minutes')
            ->toBase()->first();

        $users = $this->query($report)
            ->leftJoin('users', 'users.id', '=', 'task_worklogs.user_id')
            ->selectRaw('task_worklogs.user_id AS user_id, users.name AS name')
            ->selectRaw('COUNT(*) AS entries, COALESCE(SUM(task_worklogs.duration_minutes), 0) AS total_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN task_worklogs.is_creative = 1 THEN task_worklogs.duration_minutes ELSE 0 END), 0) AS creative_minutes')
            ->groupBy('task_worklogs.user_id', 'users.name')
            ->orderByDesc('total_minutes')
            ->orderBy('users.name')
            ->get();

        $tasks = $this->query($report)
            ->selectRaw('tasks.id AS task_id, tasks.number AS number, tasks.title AS title')
            ->selectRaw('COUNT(*) AS entries, COALESCE(SUM(task_worklogs.duration_minutes), 0) AS total_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN task_worklogs.is_creative = 1 THEN task_worklogs.duration_minutes ELSE 0 END), 0) AS creative_minutes')
            ->groupBy('tasks.id', 'tasks.number', 'tasks.title')
            ->orderByDesc('total_minutes')
            ->orderBy('tasks.number')
            ->get();

        return [
            'entries' => (int) ($totals?->entries ?? 0),
            'total_minutes' => (int) ($totals?->total_minutes ?? 0),
            'creative_entries' => (int) ($totals?->creative_entries ?? 0),
            'creative_minutes' => (int) ($totals?->creative_minutes ?? 0),
            'by_user' => $users->map(fn (TaskWorklog $row): array => [
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'name' => (string) ($row->getAttribute('name') ?? 'Former user'),
                'entries' => (int) $row->getAttribute('entries'),
                'total_minutes' => (int) $row->getAttribute('total_minutes'),
                'creative_minutes' => (int) $row->getAttribute('creative_minutes'),
            ])->all(),
            'by_task' => $tasks->map(fn (TaskWorklog $row): array => [
                'task_id' => (int) $row->getAttribute('task_id'),
                'number' => (int) $row->getAttribute('number'),
                'title' => (string) $row->getAttribute('title'),
                'entries' => (int) $row->getAttribute('entries'),
                'total_minutes' => (int) $row->getAttribute('total_minutes'),
                'creative_minutes' => (int) $row->getAttribute('creative_minutes'),
            ])->all(),
        ];
    }

    /** @return Builder<TaskWorklog> */
    private function query(Report $report): Builder
    {
        return TaskWorklog::query()
            ->join('tasks', 'tasks.id', '=', 'task_worklogs.task_id')
            ->where('tasks.project_id', $report->project_id)
            ->when($report->contributor_ids !== null, fn (Builder $query) => $query->whereIn('task_worklogs.user_id', $report->contributor_ids))
            ->whereBetween('task_worklogs.work_date', [
                $report->period_start->toDateString(),
                $report->period_end->toDateString(),
            ]);
    }
}
