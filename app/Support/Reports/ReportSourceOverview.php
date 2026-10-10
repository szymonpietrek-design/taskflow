<?php

namespace App\Support\Reports;

use App\Enums\ReportSource;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ReportSourceOverview
{
    /** @return array{tasks:int|null,messages:int|null,activity:int|null} */
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
        ];
    }
}
