<?php

namespace App\Support\Reports;

use App\Enums\ReportSource;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Support\Vcs\VcsSummaryEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ReportSourceOverview
{
    public function __construct(private readonly ReportWorklogBreakdown $worklogBreakdown, private readonly VcsSummaryEvidence $vcs) {}

    /** @return array<string, mixed> */
    public function forReport(Report $report): array
    {
        $start = CarbonImmutable::parse($report->period_start)->startOfDay();
        $end = CarbonImmutable::parse($report->period_end)->endOfDay();
        $selected = $report->sources;

        return [
            // Include existing tasks with activity in the period; a task need not
            // be newly created or completed to have contributed to the work.
            'tasks' => in_array(ReportSource::TASKS->value, $selected, true)
                ? Task::query()->where('project_id', $report->project_id)
                    ->when($report->contributor_ids !== null, fn (Builder $q) => $q->where(fn (Builder $s) => $s
                        ->whereIn('created_by', $report->contributor_ids)->orWhereIn('assignee_id', $report->contributor_ids)))
                    ->where(fn (Builder $query) => $query
                        ->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('completed_at', [$start, $end])
                        ->orWhereHas('activities', fn (Builder $activity) => $activity
                            ->whereBetween('created_at', [$start, $end])
                            ->when($report->contributor_ids !== null,
                                fn (Builder $q) => $q->whereIn('user_id', $report->contributor_ids))))
                    ->count() : null,
            'messages' => in_array(ReportSource::MESSAGES->value, $selected, true)
                ? TaskMessage::query()->whereHas('task', fn (Builder $query) => $query
                    ->where('project_id', $report->project_id))
                    ->when($report->contributor_ids !== null, fn (Builder $q) => $q->whereIn('user_id', $report->contributor_ids))
                    ->whereBetween('created_at', [$start, $end])->count() : null,
            'activity' => in_array(ReportSource::ACTIVITY->value, $selected, true)
                ? TaskActivity::query()->whereHas('task', fn (Builder $query) => $query
                    ->where('project_id', $report->project_id))
                    ->when($report->contributor_ids !== null, fn (Builder $q) => $q->whereIn('user_id', $report->contributor_ids))
                    ->whereBetween('created_at', [$start, $end])->count() : null,
            'vcs' => in_array(ReportSource::VCS->value, $selected, true)
                && $report->contributor_ids === null
                ? $this->vcs->countsForPeriod($report->project_id, $start->toDateString(), $end->toDateString()) : null,
            'worklogs' => in_array(ReportSource::WORKLOGS->value, $selected, true)
                ? $this->worklogBreakdown->forReport($report) : null,
        ];
    }
}
