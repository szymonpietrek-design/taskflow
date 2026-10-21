<?php

namespace App\Http\Controllers;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskWorklog;
use App\Support\Projects\ProjectContext;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, ProjectContext $projectContext, WorkspaceContext $workspaceContext): Response
    {
        $user = $request->user();
        $workspace = $workspaceContext->resolve($request)['current'];

        // ProjectContext drives the sidebar and knows about project-only members.
        // Recheck the actual policy before using IDs in dashboard queries.
        $navigationIds = collect($projectContext->resolve($request)['items'])->pluck('id');
        $projects = Project::query()
            ->whereIn('id', $navigationIds)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => $user->can('view', $project))
            ->values();
        $projectIds = $projects->pluck('id')->all();

        $openCategories = [TaskStatusCategory::DONE->value, TaskStatusCategory::CANCELED->value];
        $myOpenTasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('assignee_id', $user->id)
            ->whereHas('status', fn (Builder $query) => $query->whereNotIn('category', $openCategories));

        $openCount = (clone $myOpenTasks)->count();
        $overdueCount = (clone $myOpenTasks)->whereDate('due_date', '<', today())->count();
        $dueSoonCount = (clone $myOpenTasks)
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays(7))
            ->count();

        // Task worklogs are personal and scoped to the projects currently visible
        // in this workspace. Weeks start on Monday, consistent with My Work Time.
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $weekMinutes = TaskWorklog::query()
            ->where('user_id', $user->id)
            ->whereHas('task', fn (Builder $query) => $query->whereIn('project_id', $projectIds))
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<', $weekStart->copy()->addWeek()->toDateString())
            ->sum('duration_minutes');

        // All three summary links use this scoped query. The default preview is
        // short, while a focused list supports pagination without leaking tasks
        // from another project or workspace.
        $focus = $request->query('focus');
        $focus = is_string($focus) && in_array($focus, ['open', 'overdue', 'due_soon'], true)
            ? $focus
            : null;

        $attentionQuery = clone $myOpenTasks;
        if ($focus === 'overdue') {
            $attentionQuery->whereDate('due_date', '<', today());
        } elseif ($focus === 'due_soon') {
            $attentionQuery->whereDate('due_date', '>=', today())
                ->whereDate('due_date', '<=', today()->addDays(7));
        }

        $taskPage = $attentionQuery
            ->with(['project:id,key,name', 'status:id,name,category'])
            // Urgent overdue work comes first; within the remaining work,
            // earlier due dates still take precedence over priority.
            ->orderByRaw('CASE WHEN due_date < ? THEN 0 WHEN due_date IS NULL THEN 2 ELSE 1 END', [today()->toDateString()])
            ->orderByRaw('CASE WHEN due_date < ? THEN priority ELSE 0 END DESC', [today()->toDateString()])
            ->orderBy('due_date')
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->simplePaginate($focus === null ? 7 : 15)
            ->withQueryString();

        $upcoming = $taskPage->getCollection()->map(fn (Task $task): array => [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'reference' => $task->project->key.'-'.$task->number,
            'title' => $task->title,
            'status' => $task->status->name,
            'priority' => $task->priority->value,
            'category' => strtolower($task->status->category->name),
            'due_date' => $task->due_date?->toDateString(),
            'overdue' => $task->due_date !== null && $task->due_date->lt(today()),
        ])->all();

        // Small, bounded activity feed from visible projects only. Keep one entry
        // per task/event kind to avoid consecutive identical events dominating.
        $activities = TaskActivity::query()
            ->whereHas('task', fn (Builder $query) => $query->whereIn('project_id', $projectIds))
            ->with(['task:id,project_id,number,title', 'task.project:id,key', 'actor:id,name'])
            ->latest('created_at')->latest('id')->limit(24)->get()
            ->map(function (TaskActivity $activity): array {
                $from = $activity->data['from'] ?? null;
                $to = $activity->data['to'] ?? null;
                $kind = match ($activity->type) {
                    TaskActivityType::STATUS_CHANGED => 'status',
                    TaskActivityType::PRIORITY_CHANGED => 'priority',
                    TaskActivityType::ASSIGNEE_CHANGED => 'assignee',
                    TaskActivityType::DUE_DATE_CHANGED => 'due_date',
                    TaskActivityType::RELEASE_CHANGED => 'release',
                    TaskActivityType::TITLE_CHANGED => 'title',
                    TaskActivityType::DESCRIPTION_CHANGED => 'description',
                    TaskActivityType::TYPE_CHANGED => 'type',
                };

                // Send only short, user-facing values rather than raw activity data
                // (which can contain long descriptions or unrelated metadata).
                $display = static function (mixed $value, string $kind): ?string {
                    if ($value === null) {
                        return null;
                    }
                    if (is_array($value)) {
                        $value = $kind === 'priority' || $kind === 'type'
                            ? ($value['value'] ?? null)
                            : ($value['name'] ?? null);
                    }
                    return is_scalar($value) ? mb_substr((string) $value, 0, 90) : null;
                };

                return [
                    'id' => 'activity-'.$activity->id,
                    'kind' => $kind,
                    'project_id' => $activity->task->project_id,
                    'task_id' => $activity->task_id,
                    'reference' => $activity->task->project->key.'-'.$activity->task->number,
                    'task_title' => $activity->task->title,
                    'actor' => $activity->actor?->name,
                    'actor_id' => $activity->user_id,
                    'from' => $display($from, $kind),
                    'to' => $display($to, $kind),
                    'created_at' => $activity->created_at?->toIso8601String(),
                ];
            });

        $messages = TaskMessage::query()
            ->whereHas('task', fn (Builder $query) => $query->whereIn('project_id', $projectIds))
            ->with(['task:id,project_id,number,title', 'task.project:id,key', 'author:id,name'])
            ->latest('created_at')->latest('id')->limit(18)->get()
            ->map(fn (TaskMessage $message): array => [
                'id' => 'message-'.$message->id,
                'kind' => 'comment',
                'project_id' => $message->task->project_id,
                'task_id' => $message->task_id,
                'reference' => $message->task->project->key.'-'.$message->task->number,
                'task_title' => $message->task->title,
                'actor' => $message->author?->name,
                'actor_id' => $message->user_id,
                'from' => null,
                'to' => null,
                'created_at' => $message->created_at?->toIso8601String(),
            ]);

        // Merge changes made by the same actor to one task within five
        // minutes. Preserve distinct kinds of changes, but don't repeat a
        // series of identical events in the feed. Comments remain separate.
        $activityGroups = [];
        foreach ($activities->concat($messages)->sortByDesc('created_at') as $event) {
            $match = null;
            if ($event['kind'] !== 'comment') {
                foreach ($activityGroups as $index => $group) {
                    if ($group['kind'] === 'comment'
                        || $group['task_id'] !== $event['task_id']
                        || $group['actor_id'] !== $event['actor_id']
                        || abs(strtotime((string) $group['created_at']) - strtotime((string) $event['created_at'])) > 300) {
                        continue;
                    }
                    $match = $index;
                    break;
                }
            }

            if ($match !== null) {
                // Latest value for a repeated event kind is already present.
                if (! collect($activityGroups[$match]['changes'])->contains('kind', $event['kind'])) {
                    $activityGroups[$match]['changes'][] = [
                        'kind' => $event['kind'],
                        'from' => $event['from'],
                        'to' => $event['to'],
                    ];
                }
                continue;
            }

            $event['changes'] = $event['kind'] === 'comment' ? [] : [[
                'kind' => $event['kind'],
                'from' => $event['from'],
                'to' => $event['to'],
            ]];
            $activityGroups[] = $event;
        }
        $activityFeed = array_map(static function (array $item): array {
            unset($item['actor_id']);

            return $item;
        }, array_slice($activityGroups, 0, 5));

        return Inertia::render('Dashboard', [
            'workspace_name' => $workspace['name'] ?? null,
            'stats' => [
                'projects' => count($projectIds),
                'my_open_tasks' => $openCount,
                'overdue' => $overdueCount,
                'due_soon' => $dueSoonCount,
                'week_minutes' => (int) $weekMinutes,
            ],
            'my_tasks' => $upcoming,
            'task_view' => $focus ?? 'attention',
            'task_pagination' => [
                'previous_url' => $taskPage->previousPageUrl(),
                'next_url' => $taskPage->nextPageUrl(),
            ],
            // Sidebar projects are provided separately by ProjectContext.
            'activity' => $activityFeed,
        ]);
    }
}
