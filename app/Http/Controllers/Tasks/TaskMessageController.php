<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskMessageRequest;
use App\Http\Requests\Tasks\UpdateTaskMessageRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskMessageController extends Controller
{
    public function store(StoreTaskMessageRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertTaskBelongsToProject($project, $task);

        $task->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($request->validated('body')),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Message added.'),
        ]);

        return to_route('tasks.show', [$project, $task]);
    }

    public function update(
        UpdateTaskMessageRequest $request,
        Project $project,
        Task $task,
        TaskMessage $message,
    ): RedirectResponse {
        $this->assertTaskBelongsToProject($project, $task, $message);

        $message->update(['body' => trim($request->validated('body'))]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Message updated.'),
        ]);

        return to_route('tasks.show', [$project, $task]);
    }

    public function destroy(Request $request, Project $project, Task $task, TaskMessage $message): RedirectResponse
    {
        $this->assertTaskBelongsToProject($project, $task, $message);
        Gate::forUser($request->user())->authorize('delete', $message);

        $message->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Message deleted.'),
        ]);

        return to_route('tasks.show', [$project, $task]);
    }

    private function assertTaskBelongsToProject(Project $project, Task $task, ?TaskMessage $message = null): void
    {
        abort_unless($task->project_id === $project->id, 404);
        if ($message !== null) {
            abort_unless($message->task_id === $task->id, 404);
        }
    }
}
