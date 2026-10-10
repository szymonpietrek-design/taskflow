<?php

namespace App\Support\Reports;

use App\Enums\ReportSource;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskWorklog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ReportSourceOverview
{
    /** @return array{tasks:int|null,messages:int|null,activity:int|null,worklogs:array{entries:int,total_minutes:int}|null} */
    public function forReport(Report $report): array
    {
        $start = CarbonImmutable::parse($report->period_start)->startOfDay();
        $end = CarbonImmutable::parse($report->period_end)->endOfDay();
        $selected = $report->sources;

        return [
            // Tasks created OR completed within the period; count each task only once.
            'tasks' => in_array(ReportSource::TASKS->value, $selected, true)
                ? Task::query()->where('project_id', $report->project_id)
                    ->where(fn (Builder $query) => $query
                        ->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('completed_at', [$start, $end]))
                    ->count() : null,
            'messages' => in_array(ReportSource::MESSAGES->value, $selected, true)
                ? TaskMessage::query()->whereHas('task', fn (Builder $query) => $query
                    ->where('project_id', $report->project_id))
                    ->whereBetween('created_at', [$start, $end])->count() : null,
            'activity' => in_array(ReportSource::ACTIVITY->value, $selected, true)
                ? TaskActivity::query()->whereHas('task', fn (Builder $query) => $query
                    ->where('project_id', $report->project_id))
                    ->whereBetween('created_at', [$start, $end])->count() : null,
            'worklogs' => in_array(ReportSource::WORKLOGS->value, $selected, true)
                ? $this->worklogTotals($report) : null,
        ];
    }

    /** @return array{entries:int,total_minutes:int} */
    private function worklogTotals(Report $report): array
    {
        // Worklogs belong to a reporting period by work_date, not by when they were saved.
        // The task relation limits the totals to this project; no pagination is involved.
        $totals = TaskWorklog::query()
            ->whereHas('task', fn (Builder $query) => $query->where('project_id', $report->project_id))
            ->whereBetween('work_date', [
                $report->period_start->toDateString(),
                $report->period_end->toDateString(),
            ])
            ->selectRaw('COUNT(*) AS entries, COALESCE(SUM(duration_minutes), 0) AS total_minutes')
            ->toBase()
            ->first();

        return [
            'entries' => (int) ($totals?->entries ?? 0),
            'total_minutes' => (int) ($totals?->total_minutes ?? 0),
        ];
    }
}
