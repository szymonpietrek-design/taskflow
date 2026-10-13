<?php

namespace App\Http\Controllers\Worklogs;

use App\Actions\Tasks\SaveTaskWorklog;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worklogs\SaveGlobalWorklogRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskWorklog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class WorklogController extends Controller
{
    public function index(Request $request): Response
    {
        $input = Validator::make($request->query(), [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'timezone' => ['sometimes', 'required', 'timezone'],
        ])->validate();

        $timezone = $input['timezone'] ?? 'UTC';
        $date = $input['date'] ?? now($timezone)->toDateString();
        $userId = $request->user()->id;

        $weekStart = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);
        // All of the user's entries count to the limit, even if access is later removed.
        $weekTotals = TaskWorklog::query()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('work_date, SUM(duration_minutes) as minutes')
            ->groupBy('work_date')
            ->pluck('minutes', 'work_date');

        $week = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $weekTotals): array {
            $day = $weekStart->addDays($offset)->toDateString();

            return ['date' => $day, 'total_minutes' => (int) ($weekTotals[$day] ?? 0)];
        })->all();

        $daily = TaskWorklog::query()->where('user_id', $userId)->whereDate('work_date', $date);
        $totalMinutes = (int) (clone $daily)->sum('duration_minutes');

        // Match ProjectPolicy::view in SQL, without leaking private project details.
        $visible = (clone $daily)->whereHas('task.project', function (Builder $projects) use ($userId): void {
            $projects->whereHas('workspace.memberships', fn (Builder $members) => $members->where('user_id', $userId)->whereNull('suspended_at'))
                ->where(function (Builder $accessible) use ($userId): void {
                    $accessible->where('visibility', ProjectVisibility::WORKSPACE->value)
                        ->orWhereHas('memberships', fn (Builder $members) => $members->where('user_id', $userId))
                        ->orWhereHas('workspace.memberships', fn (Builder $members) => $members
                            ->where('user_id', $userId)
                            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]));
                });
        });

        $visibleMinutes = (int) (clone $visible)->sum('duration_minutes');

        // Only contributing members/admins of non-archived projects may create or edit entries.
        $writableProjects = Project::query()
            ->whereNull('archived_at')
            ->whereHas('workspace.memberships', fn (Builder $members) => $members->where('user_id', $userId)->whereNull('suspended_at'))
            ->where(function (Builder $projects) use ($userId): void {
                $projects->whereHas('memberships', fn (Builder $members) => $members->where('user_id', $userId))
                    ->orWhereHas('workspace.memberships', fn (Builder $members) => $members
                        ->where('user_id', $userId)
                        ->whereNull('suspended_at')
                        ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]));
            })
            ->with('workspace:id,name')
            ->orderBy('name')->get();
        $writableIds = $writableProjects->pluck('id')->all();

        $entries = $visible->with(['task:id,project_id,number,title', 'task.project:id,workspace_id,name,key'])
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('worklogs/Index', [
            'date' => $date,
            'timezone' => $timezone,
            'week' => $week,
            'daily_limit_minutes' => TaskWorklog::DAILY_LIMIT_MINUTES,
            'total_minutes' => $totalMinutes,
            'visible_minutes' => $visibleMinutes,
            'worklog_projects' => $writableProjects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
                'workspace_name' => $project->workspace->name,
            ])->all(),
            'entries' => [
                'data' => $entries->getCollection()->map(fn (TaskWorklog $entry): array => [
                    'id' => $entry->id,
                    'work_date' => $entry->work_date->toDateString(),
                    'duration_minutes' => $entry->duration_minutes,
                    'description' => $entry->description,
                    'is_creative' => $entry->is_creative,
                    'can_manage' => in_array($entry->task->project_id, $writableIds, true),
                    'task' => [
                        'id' => $entry->task->id,
                        'title' => $entry->task->title,
                        'reference' => $entry->task->project->key.'-'.$entry->task->number,
                    ],
                    'project' => [
                        'id' => $entry->task->project->id,
                        'name' => $entry->task->project->name,
                    ],
                ])->all(),
                'total' => $entries->total(),
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'previous_page_url' => $entries->previousPageUrl(),
                'next_page_url' => $entries->nextPageUrl(),
            ],
        ]);
    }

    /** Search tasks lazily so the global page does not download every task in every workspace. */
    public function tasks(Request $request, Project $project): JsonResponse
    {
        Validator::make($request->query(), [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ])->validate();

        abort_if($project->archived_at !== null, 403);
        Gate::forUser($request->user())->authorize('create', [Task::class, $project]);

        $search = trim((string) $request->query('search', ''));
        $tasks = Task::query()->where('project_id', $project->id)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $matched) use ($search): void {
                $matched->where('title', 'like', '%'.$search.'%')
                    ->orWhere('number', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')->limit(40)->get(['id', 'number', 'title']);

        return response()->json(['tasks' => $tasks->map(fn (Task $task): array => [
            'id' => $task->id,
            'label' => $project->key.'-'.$task->number.' · '.$task->title,
        ])->all()]);
    }

    public function store(SaveGlobalWorklogRequest $request, SaveTaskWorklog $save): RedirectResponse
    {
        $data = $request->validated();
        $task = Task::query()->findOrFail($data['task_id']);
        $save->handle($request->user(), $task, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry added.')]);

        return $this->backToDate($data);
    }

    public function update(SaveGlobalWorklogRequest $request, TaskWorklog $worklog, SaveTaskWorklog $save): RedirectResponse
    {
        $data = $request->validated();
        $save->handle($request->user(), $worklog->task, $data, $worklog);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry updated.')]);

        return $this->backToDate($data);
    }

    public function destroy(Request $request, TaskWorklog $worklog): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('delete', $worklog);
        $date = $worklog->work_date->toDateString();
        $worklog->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry deleted.')]);

        return to_route('worklogs.index', ['date' => $date, 'timezone' => $request->input('timezone', 'UTC')]);
    }

    /** @param array<string, mixed> $data */
    private function backToDate(array $data): RedirectResponse
    {
        return to_route('worklogs.index', [
            'date' => $data['work_date'],
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);
    }
}
