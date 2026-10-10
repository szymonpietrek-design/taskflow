<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskWorklogRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskWorklog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TaskWorklogController extends Controller
{
    private const DAILY_LIMIT_MINUTES = 8 * 60;

    public function store(SaveTaskWorklogRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->saveWorklog($request->user(), $task, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry added.')]);

        return to_route('tasks.show', [$project, $task]);
    }

    public function update(
        SaveTaskWorklogRequest $request,
        Project $project,
        Task $task,
        TaskWorklog $worklog,
    ): RedirectResponse {
        $this->saveWorklog($request->user(), $task, $request->validated(), $worklog);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry updated.')]);

        return to_route('tasks.show', [$project, $task]);
    }

    public function destroy(Request $request, Project $project, Task $task, TaskWorklog $worklog): RedirectResponse
    {
        abort_unless($task->project_id === $project->id && $worklog->task_id === $task->id, 404);
        Gate::forUser($request->user())->authorize('delete', $worklog);

        $worklog->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry deleted.')]);

        return to_route('tasks.show', [$project, $task]);
    }

    /** @param array<string, mixed> $data */
    private function saveWorklog(User $user, Task $task, array $data, ?TaskWorklog $worklog = null): void
    {
        DB::transaction(function () use ($user, $task, $data, $worklog): void {
            // Serialise all of a user's worklog writes, including writes on different tasks
            // and projects. An aggregate check without a lock could allow concurrent overbooking.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $duration = (int) $data['hours'] * 60 + (int) $data['minutes'];
            $existingEntries = TaskWorklog::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $data['work_date']);

            if ($worklog !== null) {
                $existingEntries->where('id', '!=', $worklog->id);
            }

            $alreadyLogged = (int) $existingEntries->sum('duration_minutes');

            if ($alreadyLogged + $duration > self::DAILY_LIMIT_MINUTES) {
                $available = max(0, self::DAILY_LIMIT_MINUTES - $alreadyLogged);
                $remaining = sprintf('%dh %dm', intdiv($available, 60), $available % 60);

                throw ValidationException::withMessages([
                    'hours' => "Daily limit: 8 hours per user across all tasks and projects. Remaining on this date: {$remaining}.",
                ]);
            }

            $attributes = [
                'work_date' => $data['work_date'],
                'duration_minutes' => $duration,
                'description' => $data['description'] ?? null,
                'is_creative' => $data['is_creative'] ?? false,
            ];

            if ($worklog !== null) {
                $worklog->update($attributes);
            } else {
                $task->worklogs()->create(['user_id' => $user->id, ...$attributes]);
            }
        });
    }
}
