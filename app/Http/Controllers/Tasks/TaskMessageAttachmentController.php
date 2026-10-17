<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskMessageAttachmentController extends Controller
{
    public function destroy(
        Request $request,
        Project $project,
        Task $task,
        TaskMessage $message,
        TaskAttachment $attachment,
    ): RedirectResponse {
        abort_unless(
            $task->project_id === $project->id
            && $message->task_id === $task->id
            && $attachment->task_id === $task->id,
            404,
        );
        Gate::forUser($request->user())->authorize('update', $message);
        abort_unless($message->attachments()->whereKey($attachment->id)->exists(), 404);

        // Unlink only. The file remains in the task library and in other messages.
        $message->attachments()->detach($attachment->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('File removed from message.')]);

        return back();
    }
}
