<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\CreateTaskFromMessage;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskFromMessageRequest;
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

class TaskFromMessageController extends Controller
{
    public function create(Request $request, Project $project, Task $task, TaskMessage $message): Response
    {
        $this->assertSource($project, $task, $message);
        Gate::forUser($request->user())->authorize('view', $task);
        Gate::forUser($request->user())->authorize('create', [Task::class, $project]);

        $request->session()->put('current_workspace_id', $project->workspace_id);

        // First nonempty line is a literal suggestion, not an AI-generated title.
        $lines = preg_split('/\R/u', trim($message->body)) ?: [];
        $firstLine = collect($lines)->map(fn (string $line): string => trim($line))->first(fn (string $line): bool => $line !== '') ?? '';

        return Inertia::render('tasks/CreateFromMessage', [
            'project' => ['id' => $project->id, 'name' => $project->name, 'key' => $project->key],
            'source_task' => ['id' => $task->id, 'reference' => $task->reference(), 'title' => $task->title],
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'author' => $message->author?->name ?? 'Deleted user',
            ],
            'suggested_title' => mb_substr($firstLine !== '' ? $firstLine : 'Task from discussion', 0, 200),
            'statuses' => $project->taskStatuses()->orderBy('position')->get()
                ->map(fn (TaskStatus $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'category' => strtolower($status->category->name),
                ])->all(),
            'assignees' => $this->assigneeOptions($project),
        ]);
    }

    public function store(
        StoreTaskFromMessageRequest $request,
        Project $project,
        Task $task,
        TaskMessage $message,
        CreateTaskFromMessage $createTaskFromMessage,
    ): RedirectResponse {
        $this->assertSource($project, $task, $message);
        $data = $request->validated();

        $created = $createTaskFromMessage->handle(
            $request->user(),
            $project,
            $task,
            $message,
            $data['title'],
            TaskType::from((int) $data['type']),
            TaskPriority::from((int) $data['priority']),
            isset($data['status_id']) ? $project->taskStatuses()->findOrFail($data['status_id']) : null,
            isset($data['assignee_id']) ? User::query()->findOrFail($data['assignee_id']) : null,
            $data['description'] ?? null,
            isset($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task created from message.'),
        ]);

        return to_route('tasks.show', [$project, $created]);
    }

    private function assertSource(Project $project, Task $task, TaskMessage $message): void
    {
        abort_unless($task->project_id === $project->id && $message->task_id === $task->id, 404);
    }

    /** @return array<int, array{id:int,name:string}> */
    private function assigneeOptions(Project $project): array
    {
        return User::query()
            ->whereNull('disabled_at')
            ->where(function ($query) use ($project): void {
                $query->whereHas('workspaceMemberships', function ($memberships) use ($project): void {
                    $memberships->where('workspace_id', $project->workspace_id)
                    ->whereNull('suspended_at')
                    ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]);
                })->orWhereHas('projectMemberships', fn ($memberships) => $memberships->where('project_id', $project->id));
                })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
