<?php

namespace App\Support\Projects;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskWorklog;
use Illuminate\Database\Eloquent\Builder;

final class ProjectOverview
{
    /**
     * An intentionally small, read-only dashboard payload. Queries are scoped to
     * the authorized project, and the activity lists are bounded before merging.
     *
     * @return array<string, mixed>
     */
    public function forProject(Project $project): array
    {
        $counts = $project->tasks()
            ->join('task_statuses', 'tasks.status_id', '=', 'task_statuses.id')
            ->selectRaw('task_statuses.category AS category, COUNT(*) AS aggregate')
            ->groupBy('task_statuses.category')
            ->pluck('aggregate', 'category');

        $total = (int) $counts->sum();
        $inProgress = (int) ($counts[TaskStatusCategory::IN_PROGRESS->value] ?? 0);
        $completed = (int) ($counts[TaskStatusCategory::DONE->value] ?? 0);
        $canceled = (int) ($counts[TaskStatusCategory::CANCELED->value] ?? 0);
        $eligible = max(0, $total - $canceled);

        $minutes = (int) TaskWorklog::query()
            ->whereHas('task', fn (Builder $query) => $query->where('project_id', $project->id))
            ->sum('duration_minutes');

        $recentTasks = $project->tasks()
            ->with('status:id,name,category')
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'reference' => $project->key.'-'.$task->number,
                'title' => $task->title,
                'status' => $task->status->name,
                'category' => strtolower($task->status->category->name),
            ])->all();

        $activities = TaskActivity::query()
            ->whereHas('task', fn (Builder $query) => $query->where('project_id', $project->id))
            ->with(['task:id,project_id,number', 'actor:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (TaskActivity $activity): array => [
                'id' => 'activity-'.$activity->id,
                'kind' => $activity->type === TaskActivityType::STATUS_CHANGED ? 'status' : 'change',
                'task_id' => $activity->task_id,
                'reference' => $project->key.'-'.$activity->task->number,
                'actor' => $activity->actor?->name,
                'minutes' => null,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        $messages = TaskMessage::query()
            ->whereHas('task', fn (Builder $query) => $query->where('project_id', $project->id))
            ->with(['task:id,project_id,number', 'author:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (TaskMessage $message): array => [
                'id' => 'message-'.$message->id,
                'kind' => 'comment',
                'task_id' => $message->task_id,
                'reference' => $project->key.'-'.$message->task->number,
                'actor' => $message->author?->name,
                'minutes' => null,
                'created_at' => $message->created_at?->toIso8601String(),
            ]);

        $worklogs = TaskWorklog::query()
            ->whereHas('task', fn (Builder $query) => $query->where('project_id', $project->id))
            ->with(['task:id,project_id,number', 'author:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (TaskWorklog $worklog): array => [
                'id' => 'worklog-'.$worklog->id,
                'kind' => 'worklog',
                'task_id' => $worklog->task_id,
                'reference' => $project->key.'-'.$worklog->task->number,
                'actor' => $worklog->author?->name,
                'minutes' => $worklog->duration_minutes,
                'created_at' => $worklog->created_at?->toIso8601String(),
            ]);

        $latestRelease = $project->type === ProjectType::APPLICATION
            ? $project->releases()
                ->where('status', ReleaseStatus::RELEASED->value)
                ->orderByDesc('version_major')
                ->orderByDesc('version_minor')
                ->orderByDesc('version_patch')
                ->first()
            : null;

        return [
            'stats' => [
                'total' => $total,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'worklog_minutes' => $minutes,
                'progress_percent' => $eligible > 0 ? (int) round($completed / $eligible * 100) : 0,
            ],
            'recent_tasks' => $recentTasks,
            'activity' => $activities->concat($messages)->concat($worklogs)
                ->sortByDesc('created_at')->take(6)->values()->all(),
            'latest_release' => $latestRelease instanceof Release ? [
                'id' => $latestRelease->id,
                'version' => $latestRelease->version,
                'name' => $latestRelease->name,
                'released_at' => $latestRelease->released_at?->toIso8601String(),
            ] : null,
        ];
    }
}
