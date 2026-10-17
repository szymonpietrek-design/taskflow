<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\ChangeTaskStatus;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTaskDetails;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\TaskType;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\FilterTasksRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\TaskWorklog;
use App\Models\User;
use App\Support\Tasks\TaskCreationOptions;
use App\Support\Tasks\TaskMarkdown;
use App\Support\Tasks\TaskMessageMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(FilterTasksRequest $request, Project $project, TaskCreationOptions $taskCreationOptions): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', [Task::class, $project]);
        $this->syncWorkspace($request, $project);

        $input = $request->validated();
        $search = trim((string) ($input['q'] ?? ''));
        $statusId = isset($input['status_id']) ? (int) $input['status_id'] : null;
        $priority = isset($input['priority']) ? (int) $input['priority'] : null;
        $assignee = (string) ($input['assignee_id'] ?? '');
        $sort = $input['sort'] ?? 'newest';

        // Aggregate across all worklog entries in SQL, without loading them per task.
        $query = $project->tasks()
            ->with(['project', 'status', 'assignee', 'parent', 'release'])
            ->withCount('worklogs as worklog_entries_count')
            ->withSum('worklogs as worklog_total_minutes', 'duration_minutes');

        if ($search !== '') {
            // Treat %, _ and ! as literal characters rather than LIKE wildcards.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $referenceNumber = null;
            if (preg_match('/^'.preg_quote($project->key, '/').'-(\d+)$/i', $search, $matches)) {
                $referenceNumber = (int) $matches[1];
            }

            $query->where(function (Builder $builder) use ($pattern, $referenceNumber): void {
                $builder->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]);
                if ($referenceNumber !== null) {
                    $builder->orWhere('number', $referenceNumber);
                }
            });
        }

        if ($statusId !== null) {
            $query->where('status_id', $statusId);
        }
        if ($priority !== null) {
            $query->where('priority', $priority);
        }
        if ($assignee === 'unassigned') {
            $query->whereNull('assignee_id');
        } elseif ($assignee !== '') {
            $query->where('assignee_id', (int) $assignee);
        }

        match ($sort) {
            'oldest' => $query->orderBy('number'),
            'priority' => $query->orderByDesc('priority')->orderByDesc('number'),
            'due_date' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('number'),
            'title' => $query->orderBy('title')->orderByDesc('number'),
            default => $query->orderByDesc('number'),
        };

        $tasks = $query->paginate(15)->withQueryString();
        $canCreate = $request->user()->can('create', [Task::class, $project]);
        $creationOptions = $canCreate ? $taskCreationOptions->forProject($project) : null;

        return Inertia::render('tasks/Index', [
            'project' => $this->projectData($project),
            'tasks' => $tasks->getCollection()->map(fn (Task $task): array => $this->taskData($task) + [
                'worklog_total_minutes' => (int) $task->worklog_total_minutes,
                'worklog_entries_count' => (int) $task->worklog_entries_count,
            ])->all(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
                'previous_page_url' => $tasks->previousPageUrl(),
                'next_page_url' => $tasks->nextPageUrl(),
            ],
            'filters' => [
                'q' => $search,
                'status_id' => $statusId === null ? '' : (string) $statusId,
                'priority' => $priority === null ? '' : (string) $priority,
                'assignee_id' => $assignee,
                'sort' => $sort,
            ],
            'statuses' => $creationOptions['statuses'] ?? $this->statusOptions($project),
            'filter_assignees' => User::query()
                ->whereIn('id', $project->tasks()->whereNotNull('assignee_id')->select('assignee_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all(),
            // The list and project overview share one task-creation dialog.
            // Keep all options scoped to this project and to users who can create.
            'parent_tasks' => $creationOptions['parent_tasks'] ?? [],
            'assignees' => $creationOptions['assignees'] ?? [],
            'can_create' => $canCreate,
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

    public function show(Request $request, Project $project, Task $task, TaskMarkdown $markdown, TaskMessageMarkdown $messageMarkdown): Response
    {
        abort_unless($task->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('view', $task);
        $this->syncWorkspace($request, $project);

        $task->load(['project', 'status', 'assignee', 'creator', 'parent', 'subtasks.status', 'subtasks.assignee', 'sourceTask', 'sourceMessage', 'release']);

        $messages = $task->messages()
            ->with(['author:id,name', 'attachments'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $canPostMessage = $request->user()->can('create', [TaskMessage::class, $task]);
        $canUpdate = $request->user()->can('update', $task);
        $attachments = $task->attachments()->with('uploader:id,name')->orderByDesc('id')->get();

        $worklogs = $task->worklogs()
            ->with('author:id,name')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'worklog_page')
            ->withQueryString();
        $totalLoggedMinutes = (int) $task->worklogs()->sum('duration_minutes');
        $canLogWork = $request->user()->can('create', [TaskWorklog::class, $task]);

        return Inertia::render('tasks/Show', [
            'project' => $this->projectData($project),
            'task' => $this->taskData($task) + [
                'description' => $task->description,
                'description_html' => $markdown->render($task->description),
                'source_task' => $task->sourceTask !== null && $task->sourceTask->project_id === $project->id
                    && $request->user()->can('view', $task->sourceTask) ? [
                        'id' => $task->sourceTask->id,
                        'reference' => $project->key.'-'.$task->sourceTask->number,
                        'message_id' => $task->sourceMessage?->id,
                    ] : null,
                'creator' => [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name,
                ],
                'subtasks' => $task->subtasks->sortBy('number')->values()->map(fn (Task $child): array => [
                    'id' => $child->id,
                    'reference' => $project->key.'-'.$child->number,
                    'title' => $child->title,
                    'status' => [
                        'id' => $child->status->id,
                        'name' => $child->status->name,
                        'category' => strtolower($child->status->category->name),
                    ],
                    'assignee' => $child->assignee === null ? null : [
                        'id' => $child->assignee->id,
                        'name' => $child->assignee->name,
                    ],
                    'due_date' => $child->due_date?->toDateString(),
                ])->all(),
                'subtask_progress' => [
                    'total' => $task->subtasks->count(),
                    'done' => $task->subtasks->filter(
                        fn (Task $child): bool => $child->status->category === TaskStatusCategory::DONE,
                    )->count(),
                ],
            ],
            'attachments' => $attachments->map(fn ($attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->size,
                'mime_type' => $attachment->mime_type,
                'is_image' => $attachment->isImage(),
                'uploader' => $attachment->uploader?->name,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ])->all(),
            'statuses' => $this->statusOptions($project),
            'can_update' => $canUpdate,
            'can_create_subtask' => $task->parent_id === null
                && $request->user()->can('create', [Task::class, $project]),
            'assignees' => $canUpdate ? $this->eligibleAssignees($project) : [],
            'can_log_work' => $canLogWork,
            'worklog_total_minutes' => $totalLoggedMinutes,
            'worklogs' => [
                'data' => $worklogs->getCollection()->map(fn (TaskWorklog $entry): array => [
                    'id' => $entry->id,
                    'work_date' => $entry->work_date->toDateString(),
                    'duration_minutes' => $entry->duration_minutes,
                    'description' => $entry->description,
                    'is_creative' => $entry->is_creative,
                    'author' => $entry->author === null ? null : [
                        'id' => $entry->author->id,
                        'name' => $entry->author->name,
                    ],
                    'can_update' => $request->user()->can('update', $entry),
                    'can_delete' => $request->user()->can('delete', $entry),
                ])->all(),
                'total' => $worklogs->total(),
                'current_page' => $worklogs->currentPage(),
                'last_page' => $worklogs->lastPage(),
                'previous_page_url' => $worklogs->previousPageUrl(),
                'next_page_url' => $worklogs->nextPageUrl(),
            ],
            'can_post_message' => $canPostMessage,
            'messages' => $messages->map(fn (TaskMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'body_html' => $messageMarkdown->render($message->body, $project->id, $task->id, $message->attachments),
                'attachments' => $message->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'is_image' => $attachment->isImage(),
                    'uploader' => null,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                ])->all(),
                'author' => $message->author === null ? null : [
                    'id' => $message->author->id,
                    'name' => $message->author->name,
                ],
                'created_at' => $message->created_at?->toIso8601String(),
                'updated_at' => $message->updated_at?->toIso8601String(),
                'can_update' => $canPostMessage && $message->user_id === $request->user()->id,
                'can_delete' => $canPostMessage && $message->user_id === $request->user()->id,
                'can_create_task' => $request->user()->can('create', [Task::class, $project]),
            ])->all(),
        ]);
    }

    public function update(
        UpdateTaskRequest $request,
        Project $project,
        Task $task,
        UpdateTaskDetails $updateTaskDetails,
    ): RedirectResponse {
        abort_unless($task->project_id === $project->id, 404);

        $data = $request->validated();

        $updateTaskDetails->handle(
            $request->user(),
            $task,
            $data['title'],
            $data['description'] ?? null,
            TaskPriority::from((int) $data['priority']),
            isset($data['assignee_id']) ? User::query()->findOrFail($data['assignee_id']) : null,
            isset($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task updated.'),
        ]);

        return to_route('tasks.show', [$project, $task]);
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
            'release' => $task->release === null ? null : [
                'id' => $task->release->id,
                'version' => $task->release->version,
            ],
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
