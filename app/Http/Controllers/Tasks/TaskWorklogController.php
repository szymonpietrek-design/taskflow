<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskWorklogRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskWorklog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskWorklogController extends Controller
{
    public function store(SaveTaskWorklogRequest $request, Project $project, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $task->worklogs()->create([
            'user_id' => $request->user()->id,
            'work_date' => $data['work_date'],
            'duration_minutes' => (int) $data['hours'] * 60 + (int) $data['minutes'],
            'description' => $data['description'] ?? null,
            'is_creative' => $data['is_creative'] ?? false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Worklog entry added.')]);

        return to_route('tasks.show', [$project, $task]);
    }

    public function update(
        SaveTaskWorklogRequest $request,
        Project $project,
        Task $task,
        TaskWorklog $worklog,
    ): RedirectResponse {
        $data = $request->validated();
        $worklog->update([
            'work_date' => $data['work_date'],
            'duration_minutes' => (int) $data['hours'] * 60 + (int) $data['minutes'],
            'description' => $data['description'] ?? null,
            'is_creative' => $data['is_creative'] ?? false,
        ]);

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
}
