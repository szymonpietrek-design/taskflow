<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskAttachmentsRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Validation\ValidationException;
use App\Support\Attachments\PrivateAttachmentStorage;
use App\Support\Attachments\TaskAttachmentUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TaskAttachmentController extends Controller
{
    public function store(
        StoreTaskAttachmentsRequest $request,
        Project $project,
        Task $task,
        PrivateAttachmentStorage $storage,
        TaskAttachmentUpload $upload,
    ): RedirectResponse {
        $files = $request->validated('files');

        $storedPaths = [];

        try {
            DB::transaction(function () use ($files, $task, $request, $upload, &$storedPaths): void {
                foreach ($files as $file) {
                    $attachment = $upload->store($task, $request->user(), $file);
                    $storedPaths[] = $attachment->path;
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $storage->delete($path);
            }
            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachments added.')]);

        return back();
    }

    public function download(Request $request, Project $project, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $project, $task, $attachment);
        abort_unless(Storage::disk(PrivateAttachmentStorage::DISK)->exists($attachment->path), 404);

        return Storage::disk(PrivateAttachmentStorage::DISK)->download(
            $attachment->path,
            $attachment->original_name,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
        );
    }

    public function preview(Request $request, Project $project, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $project, $task, $attachment);
        abort_unless($attachment->isImage(), 404);
        abort_unless(Storage::disk(PrivateAttachmentStorage::DISK)->exists($attachment->path), 404);

        return Storage::disk(PrivateAttachmentStorage::DISK)->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'inline',
        );
    }

    public function destroy(
        Request $request,
        Project $project,
        Task $task,
        TaskAttachment $attachment,
        PrivateAttachmentStorage $storage,
    ): RedirectResponse {
        $this->authorizeAttachment($request, $project, $task, $attachment, true);

        if ($attachment->messages()->exists() || preg_match('/\]\(attachment:'.preg_quote((string) $attachment->id, '/').'\)/i', (string) $task->description)) {
            throw ValidationException::withMessages([
                'attachment' => __('This file is still used by messages. Remove it from those messages first.'),
            ]);
        }

        $path = $attachment->path;
        $attachment->delete();
        $storage->delete($path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachment removed.')]);

        return back();
    }

    private function authorizeAttachment(
        Request $request,
        Project $project,
        Task $task,
        TaskAttachment $attachment,
        bool $edit = false,
    ): void {
        abort_unless($task->project_id === $project->id && $attachment->task_id === $task->id, 404);
        Gate::forUser($request->user())->authorize($edit ? 'update' : 'view', $task);
    }
}
