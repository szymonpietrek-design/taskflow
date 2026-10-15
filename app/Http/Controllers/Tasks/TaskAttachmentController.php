<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskAttachmentsRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Support\Attachments\PrivateAttachmentStorage;
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
    ): RedirectResponse {
        $files = $request->validated('files');

        $storedPaths = [];

        try {
            DB::transaction(function () use ($files, $task, $request, $storage, &$storedPaths): void {
                foreach ($files as $file) {
                    $path = $storage->store($file, 'task-attachments/'.$task->id);
                    $storedPaths[] = $path;

                    // Normalize client-provided names without relying on fragile regex delimiter escaping.
                    $originalName = str_replace(['/', '\\'], '_', $file->getClientOriginalName());
                    $originalName = preg_replace('/[\x00-\x1F\x7F]/', '_', $originalName) ?: 'file';

                    $task->attachments()->create([
                        'uploaded_by' => $request->user()->id,
                        'original_name' => mb_substr($originalName, 0, 255),
                        'path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
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
