<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Support\Tasks\TaskMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskMarkdownPreviewController extends Controller
{
    public function __invoke(Request $request, Project $project, Task $task, TaskMarkdown $markdown): JsonResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('update', $task);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        return response()->json([
            'html' => $markdown->render($data['description'] ?? null, $project->id, $task->id, $task->attachments),
        ])->header('Cache-Control', 'no-store');
    }
}
