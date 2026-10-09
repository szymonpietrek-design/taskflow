<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\ChangeTaskStatus;
use App\Actions\Tasks\CreateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', [Task::class, $project]);
        $this->syncWorkspace($request, $project);

        $tasks = $project->tasks()
            ->with(['project', 'status', 'assignee', 'parent'])
            ->orderBy('position')
            ->orderBy('number')
            ->get();

        return Inertia::render('tasks/Index', [
            'project' => $this->projectData($project),
            'tasks' => $tasks->map(fn (Task $task): array => $this->taskData($task))->all(),
            'statuses' => $this->statusOptions($project),
            'assignees' => $request->user()->can('create', [Task::class, $project])
                ? $this->eligibleAssignees($project)
                : [],
            'can_create' => $request->user()->can('create', [Task::class, $project]),
        ]);
    }

    public function store(StoreTaskRequest $request, Project $project, CreateTask $createTask): RedirectResponse
    {
        $data = $request->validated();

        $task = $createTask->handle(
            $request->user(),
            $project,
            $data['title'],
            TaskType::from((int) $data['type']),
            TaskPriority::from((int) $data['priority']),
            isset($data['status_id']) ? TaskStatus::query()->findOrFail($data['status_id']) : null,
            isset($data['assignee_id']) ? User::query()->findOrFail($data['assignee_id']) : null,
            isset($data['parent_id']) ? Task::query()->findOrFail($data['parent_id']) : null,
            $data['description'] ?? null,
            isset($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task created.'),
        ]);

        return to_route('tasks.show', [$project, $task]);
    }

    public function show(Request $request, Project $project, Task $task): Response
    {
        abort_unless($task->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('view', $task);
        $this->syncWorkspace($request, $project);

        $task->load(['project', 'status', 'assignee', 'creator', 'parent', 'subtasks.status']);

        $messages = $task->messages()
            ->with('author:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $canPostMessage = $request->user()->can('create', [TaskMessage::class, $task]);

        return Inertia::render('tasks/Show', [
            'project' => $this->projectData($project),
            'task' => $this->taskData($task) + [
                'description' => $task->description,
                'creator' => [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name,
                ],
                'subtasks' => $task->subtasks->map(fn (Task $child): array => [
                    'id' => $child->id,
                    'reference' => $project->key.'-'.$child->number,
                    'title' => $child->title,
                    'status' => $child->status->name,
                ])->all(),
            ],
            'statuses' => $this->statusOptions($project),
            'can_update' => $request->user()->can('update', $task),
            'can_post_message' => $canPostMessage,
            'messages' => $messages->map(fn (TaskMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'author' => $message->author === null ? null : [
                    'id' => $message->author->id,
                    'name' => $message->author->name,
                ],
                'created_at' => $message->created_at?->toIso8601String(),
                'updated_at' => $message->updated_at?->toIso8601String(),
                'can_update' => $canPostMessage && $message->user_id === $request->user()->id,
                'can_delete' => $canPostMessage && $message->user_id === $request->user()->id,
            ])->all(),
        ]);
    }

    public function updateStatus(
        UpdateTaskStatusRequest $request,
        Project $project,
        Task $task,
        ChangeTaskStatus $changeTaskStatus,
    ): RedirectResponse {
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validated();
        $status = $project->taskStatuses()->findOrFail($validated['status_id']);
        $changeTaskStatus->handle($request->user(), $task, $status);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task status updated.'),
        ]);

        return to_route('tasks.show', [$project, $task]);
    }

    /** @return array{id:int,name:string,key:string} */
    private function projectData(Project $project): array
    {
        return ['id' => $project->id, 'name' => $project->name, 'key' => $project->key];
    }

    /** @return array<string,mixed> */
    private function taskData(Task $task): array
    {
        return [
            'id' => $task->id,
            'reference' => $task->reference(),
            'title' => $task->title,
            'type' => $task->type->value,
            'type_label' => ucfirst(strtolower($task->type->name)),
            'priority' => $task->priority->value,
            'priority_label' => ucfirst(strtolower($task->priority->name)),
            'status' => [
                'id' => $task->status->id,
                'name' => $task->status->name,
                'category' => strtolower($task->status->category->name),
            ],
            'assignee' => $task->assignee === null ? null : [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
            ],
            'parent' => $task->parent === null ? null : [
                'id' => $task->parent->id,
                'reference' => $task->project->key.'-'.$task->parent->number,
                'title' => $task->parent->title,
            ],
            'due_date' => $task->due_date?->toDateString(),
            'completed_at' => $task->completed_at?->toIso8601String(),
        ];
    }

    /** @return array<int,array{id:int,name:string,category:string}> */
    private function statusOptions(Project $project): array
    {
        return $project->taskStatuses()
            ->orderBy('position')
            ->get()
            ->map(fn (TaskStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'category' => strtolower($status->category->name),
            ])
            ->all();
    }

    /** @return array<int,array{id:int,name:string}> */
    private function eligibleAssignees(Project $project): array
    {
        // Project members still need workspace membership, even for private projects.
        return User::query()
            ->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $project->workspace_id))
            ->where(function ($query) use ($project): void {
                $query->whereHas('workspaceMemberships', function ($memberships) use ($project): void {
                    $memberships->where('workspace_id', $project->workspace_id)
                        ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]);
                })->orWhereHas('projectMemberships', fn ($memberships) => $memberships->where('project_id', $project->id));
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    private function syncWorkspace(Request $request, Project $project): void
    {
        $request->session()->put('current_workspace_id', $project->workspace_id);
    }
}
