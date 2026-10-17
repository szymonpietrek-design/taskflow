<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Support\Attachments\TaskAttachmentUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskInlineAttachmentController extends Controller
{
    public function __invoke(Request $request, Project $project, Task $task, TaskAttachmentUpload $upload): JsonResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        $user = $request->user();
        abort_unless($user->can('update', $task) || $user->can('create', [TaskMessage::class, $task]), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp'],
        ]);
        $attachment = $upload->store($task, $user, $data['file']);

        return response()->json([
            'attachment' => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->size,
                'mime_type' => $attachment->mime_type,
                'is_image' => $attachment->isImage(),
                'uploader' => $user->name,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ],
        ], 201)->header('Cache-Control', 'no-store');
    }
}
