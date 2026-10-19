<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskMessageRequest;
use App\Http\Requests\Tasks\UpdateTaskMessageRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Support\Attachments\PrivateAttachmentStorage;
use App\Support\Attachments\TaskAttachmentUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

class TaskMessageController extends Controller
{
    public function store(
        StoreTaskMessageRequest $request,
        Project $project,
        Task $task,
        TaskAttachmentUpload $upload,
        PrivateAttachmentStorage $storage,
    ): RedirectResponse {
        $this->assertTaskBelongsToProject($project, $task);
        $data = $request->validated();
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $task, $data, $upload, &$storedPaths): void {
                $message = $task->messages()->create([
                    'user_id' => $request->user()->id,
                    'body' => trim((string) ($data['body'] ?? '')),
                ]);
                $ids = $data['attachment_ids'] ?? [];

                foreach ($data['files'] ?? [] as $file) {
                    $attachment = $upload->store($task, $request->user(), $file);
                    $storedPaths[] = $attachment->path;
                    $ids[] = $attachment->id;
                }
                $message->attachments()->sync($ids);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $storage->delete($path);
            }
            throw $exception;
        }

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

        $data = $request->validated();
        DB::transaction(function () use ($message, $data): void {
            $message->update(['body' => trim($data['body'])]);
            // The edit form submits the complete selection. An empty selection
            // removes all links, without deleting files from the task library.
            // Requests without the flag retain the add-only behavior.
            if ($data['sync_attachments'] ?? false) {
                $message->attachments()->sync($data['attachment_ids'] ?? []);
            } elseif (! empty($data['attachment_ids'])) {
                $message->attachments()->syncWithoutDetaching($data['attachment_ids']);
            }
        });

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
