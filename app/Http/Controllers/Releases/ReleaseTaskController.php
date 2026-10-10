<?php

namespace App\Http\Controllers\Releases;

use App\Actions\Releases\SetTaskRelease;
use App\Actions\Releases\AttachCompletedTasks;
use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Releases\AttachReleaseTaskRequest;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class ReleaseTaskController extends Controller
{
    public function store(
        AttachReleaseTaskRequest $request,
        Project $project,
        Release $release,
        SetTaskRelease $setTaskRelease,
    ): RedirectResponse {
        $this->checkRelease($request, $project, $release);
        $task = $project->tasks()->findOrFail($request->validated('task_id'));

        try {
            $setTaskRelease->handle($request->user(), $task, $release);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task added to release.')]);

        return to_route('releases.show', [$project, $release]);
    }

    public function attachCompleted(
        Request $request,
        Project $project,
        Release $release,
        AttachCompletedTasks $attachCompletedTasks,
    ): RedirectResponse {
        $this->checkRelease($request, $project, $release);

        try {
            $count = $attachCompletedTasks->handle($request->user(), $release);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['release' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 0 ? __('No completed tasks to add.')
                : __('Added :count completed tasks to release.', ['count' => $count]),
        ]);

        return to_route('releases.show', [$project, $release]);
    }

    public function destroy(
        Request $request,
        Project $project,
        Release $release,
        Task $task,
        SetTaskRelease $setTaskRelease,
    ): RedirectResponse {
        $this->checkRelease($request, $project, $release);
        abort_unless($task->project_id === $project->id && $task->release_id === $release->id, 404);

        try {
            $setTaskRelease->handle($request->user(), $task, null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['release' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task removed from release.')]);

        return to_route('releases.show', [$project, $release]);
    }

    private function checkRelease(Request $request, Project $project, Release $release): void
    {
        Gate::forUser($request->user())->authorize('view', $project);
        abort_unless($project->type === ProjectType::APPLICATION, 404);
        abort_unless($release->project_id === $project->id, 404);
    }
}
