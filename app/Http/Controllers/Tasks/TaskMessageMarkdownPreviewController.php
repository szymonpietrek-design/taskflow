<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskMessage;
use App\Support\Tasks\TaskMessageMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskMessageMarkdownPreviewController extends Controller
{
    public function create(Request $request, Project $project, Task $task, TaskMessageMarkdown $markdown): JsonResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('create', [TaskMessage::class, $task]);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
            'attachment_ids' => ['nullable', 'array', 'max:20'],
            'attachment_ids.*' => ['required', 'integer', 'distinct', Rule::exists('task_attachments', 'id')->where('task_id', $task->id)],
        ]);

        $files = TaskAttachment::query()
            ->where('task_id', $task->id)
            ->whereIn('id', $data['attachment_ids'] ?? [])
            ->get();

        return $this->response($markdown, $data['body'] ?? null, $project, $task, $files);
    }

    public function edit(Request $request, Project $project, Task $task, TaskMessage $message, TaskMessageMarkdown $markdown): JsonResponse
    {
        abort_unless($task->project_id === $project->id && $message->task_id === $task->id, 404);
        Gate::forUser($request->user())->authorize('update', $message);

        $data = $request->validate(['body' => ['nullable', 'string', 'max:10000']]);

        return $this->response($markdown, $data['body'] ?? null, $project, $task, $message->attachments);
    }

    /** @param iterable<TaskAttachment> $attachments */
    private function response(TaskMessageMarkdown $markdown, ?string $body, Project $project, Task $task, iterable $attachments): JsonResponse
    {
        return response()->json([
            'html' => $markdown->render($body, $project->id, $task->id, $attachments),
        ])->header('Cache-Control', 'no-store');
    }
}
