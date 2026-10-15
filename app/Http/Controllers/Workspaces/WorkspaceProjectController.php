<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WorkspaceProjectController extends Controller
{
    public function store(
        StoreProjectRequest $request,
        Workspace $workspace,
        CreateProject $createProject,
    ): RedirectResponse {
        $validated = $request->validated();

        $createProject->handle(
            $request->user(),
            $workspace,
            $validated['name'],
            $validated['key'],
            ProjectType::from((int) $validated['type']),
            ProjectVisibility::from((int) $validated['visibility']),
            $validated['description'] ?? null,
        );

        // Keep the sidebar consistent with the workspace where the project was added.
        $request->session()->put('current_workspace_id', $workspace->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Project created.'),
        ]);

        return back();
    }

    public function update(
        UpdateProjectRequest $request,
        Workspace $workspace,
        Project $project,
    ): RedirectResponse {
        abort_unless($project->workspace_id === $workspace->id, 404);

        $validated = $request->validated();
        $project->update([
            'name' => trim($validated['name']),
            'description' => isset($validated['description'])
                ? (trim($validated['description']) ?: null)
                : null,
            'visibility' => ProjectVisibility::from((int) $validated['visibility']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Project updated.'),
        ]);

        return back();
    }
}
