<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\ManageTaskStatuses;
use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskStatusRequest;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TaskStatusController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        $request->session()->put('current_workspace_id', $project->workspace_id);

        return Inertia::render('projects/Statuses', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
            ],
            'can_manage' => $project->archived_at === null && $request->user()->can('update', $project),
            'statuses' => $project->taskStatuses()
                ->withCount('tasks')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (TaskStatus $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'category' => $status->category->value,
                    'category_label' => ucwords(strtolower(str_replace('_', ' ', $status->category->name))),
                    'task_count' => $status->tasks_count,
                ])->all(),
        ]);
    }

    public function store(SaveTaskStatusRequest $request, Project $project, ManageTaskStatuses $manager): RedirectResponse
    {
        $data = $request->validated();

        return $this->perform(fn () => $manager->create(
            $request->user(), $project, $data['name'], TaskStatusCategory::from((int) $data['category']),
        ), $project);
    }

    public function update(SaveTaskStatusRequest $request, Project $project, TaskStatus $status, ManageTaskStatuses $manager): RedirectResponse
    {
        $data = $request->validated();

        return $this->perform(fn () => $manager->update(
            $request->user(), $project, $status, $data['name'], TaskStatusCategory::from((int) $data['category']),
        ), $project);
    }

    public function move(Request $request, Project $project, TaskStatus $status, ManageTaskStatuses $manager): RedirectResponse
    {
        abort_unless($status->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('update', $project);

        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        return $this->perform(fn () => $manager->move($request->user(), $project, $status, $data['direction']), $project);
    }

    public function destroy(Request $request, Project $project, TaskStatus $status, ManageTaskStatuses $manager): RedirectResponse
    {
        abort_unless($status->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('update', $project);

        return $this->perform(fn () => $manager->delete($request->user(), $project, $status), $project);
    }

    private function perform(callable $action, Project $project): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => __($exception->getMessage())]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project statuses updated.')]);

        return to_route('projects.statuses.index', $project);
    }
}
