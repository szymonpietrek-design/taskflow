<?php

namespace App\Support\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportSource;
use App\Enums\TaskStatusCategory;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskWorklog;
use App\Support\Vcs\VcsSummaryEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use JsonException;

final class ReportGenerationContext
{
    public function __construct(private readonly ReportWorklogBreakdown $worklogs, private readonly VcsSummaryEvidence $vcs) {}

    /** @throws JsonException */
    public function build(Report $report): string
    {
        $start = CarbonImmutable::parse($report->period_start)->startOfDay();
        $end = CarbonImmutable::parse($report->period_end)->endOfDay();
        $selected = $report->sources;

        if (in_array(ReportSource::VCS->value, $selected, true)
            && ($report->contributor_ids !== null || $report->format === ReportFormat::WORK_REPORT)) {
            throw new InvalidArgumentException(__('VCS source is only supported for unfiltered project or technical reports.'));
        }

        $data = [
            'project' => $report->project->name,
            'project_key' => $report->project->key,
            'report_title' => $report->title,
            'report_format' => $report->format->value,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'selected_sources' => $selected,
            'contributor_ids' => $report->contributor_ids,
        ];

        if (in_array(ReportSource::TASKS->value, $selected, true)) {
            $tasks = Task::query()->where('project_id', $report->project_id)
                ->when($report->contributor_ids !== null, fn (Builder $q) => $q->where(fn (Builder $s) => $s
                    ->whereIn('created_by', $report->contributor_ids)->orWhereIn('assignee_id', $report->contributor_ids)))
                ->where(fn (Builder $query) => $query
                    ->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('completed_at', [$start, $end])
                    ->orWhereHas('activities', fn (Builder $activity) => $activity
                        ->whereBetween('created_at', [$start, $end])
                        ->when($report->contributor_ids !== null,
                            fn (Builder $query) => $query->whereIn('user_id', $report->contributor_ids))))
                ->with('status:id,name,category')
                ->withExists(['activities as changed_in_period' => fn (Builder $activity) => $activity
                    ->whereBetween('created_at', [$start, $end])
                    ->when($report->contributor_ids !== null,
                        fn (Builder $query) => $query->whereIn('user_id', $report->contributor_ids))])
                ->orderBy('id')->limit(41)->get();
            $this->assertLimit($tasks->count(), 40, 'tasks');
            $data['tasks'] = $tasks->map(fn (Task $task): array => [
                'reference' => $report->project->key.'-'.$task->number,
                'title' => $this->shorten($task->title, 200),
                'description' => $this->shorten($task->description, 1200),
                'type' => $task->type->name,
                'status' => $task->status?->name,
                'created_at' => $task->created_at?->toDateString(),
                'completed_at' => $task->completed_at?->toDateString(),
                'changed_in_period' => (bool) $task->changed_in_period,
                'completed_in_period' => $task->completed_at !== null
                    && $task->status?->category === TaskStatusCategory::DONE
                    && $task->completed_at->betweenIncluded($start, $end),
            ])->all();
        }

        if (in_array(ReportSource::MESSAGES->value, $selected, true)) {
            $messages = TaskMessage::query()->whereHas('task', fn (Builder $query) => $query->where('project_id', $report->project_id))
                ->when($report->contributor_ids !== null, fn (Builder $q) => $q->whereIn('user_id', $report->contributor_ids))
                ->whereBetween('created_at', [$start, $end])->with('task:id,project_id,number')
                ->orderBy('id')->limit(81)->get();
            $this->assertLimit($messages->count(), 80, 'messages');
            $data['messages'] = $messages->map(fn (TaskMessage $message): array => [
                'task' => $report->project->key.'-'.$message->task->number,
                'date' => $message->created_at?->toDateString(),
                'text' => $this->shorten($message->body, 600),
            ])->all();
        }

        if (in_array(ReportSource::ACTIVITY->value, $selected, true)) {
            $activities = TaskActivity::query()->whereHas('task', fn (Builder $query) => $query->where('project_id', $report->project_id))
                ->when($report->contributor_ids !== null, fn (Builder $q) => $q->whereIn('user_id', $report->contributor_ids))
                ->whereBetween('created_at', [$start, $end])->with('task:id,project_id,number')
                ->orderBy('id')->limit(101)->get();
            $this->assertLimit($activities->count(), 100, 'activity events');
            // Activity history can contain full before/after Markdown snapshots, attachment
            // paths and repeated versions of the same description. None is needed to
            // summarize work. Keep counts and a bounded set of meaningful transitions.
            $counts = [];
            $notable = [];
            foreach ($activities as $activity) {
                $type = $activity->type->name;
                $counts[$type] = ($counts[$type] ?? 0) + 1;
                if (! in_array($type, ['STATUS_CHANGED', 'DUE_DATE_CHANGED', 'TYPE_CHANGED', 'PRIORITY_CHANGED'], true)
                    || count($notable) >= 30) {
                    continue;
                }
                $changes = $activity->data ?? [];
                $notable[] = [
                    'task' => $report->project->key.'-'.$activity->task->number,
                    'date' => $activity->created_at?->toDateString(),
                    'type' => $type,
                    'from' => $this->safeActivityValue($changes['from'] ?? null),
                    'to' => $this->safeActivityValue($changes['to'] ?? null),
                ];
            }
            ksort($counts);
            $data['activity'] = [
                'total_events' => $activities->count(),
                'counts_by_type' => $counts,
                'notable_events' => $notable,
            ];
        }

        if (in_array(ReportSource::WORKLOGS->value, $selected, true)) {
            $entries = TaskWorklog::query()->whereHas('task', fn (Builder $query) => $query->where('project_id', $report->project_id))
                ->when($report->contributor_ids !== null, fn (Builder $q) => $q->whereIn('user_id', $report->contributor_ids))
                ->where('work_date', '>=', $start->toDateString())
                ->where('work_date', '<', $end->addDay()->toDateString())
                ->with(['task:id,project_id,number,title', 'author:id,name'])
                ->orderBy('work_date')->orderBy('id')->limit(121)->get();
            $this->assertLimit($entries->count(), 120, 'worklog entries');
            $breakdown = $this->worklogs->forReport($report);
            $data['worklogs'] = [
                'entries' => $breakdown['entries'],
                'total_minutes' => $breakdown['total_minutes'],
                'creative_minutes' => $breakdown['creative_minutes'],
                'by_user' => $breakdown['by_user'],
                'by_task' => $breakdown['by_task'],
                'details' => $entries->map(fn (TaskWorklog $entry): array => [
                    'id' => $entry->id,
                    'task' => $report->project->key.'-'.$entry->task->number,
                    'task_title' => $this->shorten($entry->task->title, 200),
                    'contributor' => $entry->author?->name ?? 'Former contributor',
                    'date' => $entry->work_date->toDateString(),
                    'minutes' => $entry->duration_minutes,
                    'creative' => $entry->is_creative,
                    'description' => $this->shorten($entry->description, 450),
                ])->all(),
            ];
        }

        if (in_array(ReportSource::VCS->value, $selected, true)) {
            $data['vcs'] = $this->vcs->forPeriod($report->project_id, $start->toDateString(), $end->toDateString());
        }

        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($payload) > 90000) {
            throw new InvalidArgumentException(__('Report source data is too large for one AI request. Choose a shorter period or fewer sources.'));
        }

        return $payload;
    }

    /** Extract only human-readable scalar labels; never serialize raw activity payloads. */
    private function safeActivityValue(mixed $value): ?string
    {
        if (is_array($value)) {
            // Do not pass internal IDs, nested metadata or arbitrary serialized values.
            $value = $value['name'] ?? null;
        }
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        return $this->shorten((string) $value, 80);
    }

    private function assertLimit(int $count, int $limit, string $source): void
    {
        if ($count > $limit) {
            throw new InvalidArgumentException("Too many {$source} for one AI request (maximum {$limit}). Choose a shorter period or fewer sources.");
        }
    }

    private function shorten(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).' [truncated]' : $value;
    }
}
