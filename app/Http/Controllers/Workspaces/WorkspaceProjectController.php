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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
    public function archive(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        abort_unless($project->workspace_id === $workspace->id, 403);
        Gate::forUser($request->user())->authorize('archive', $project);

        DB::transaction(function () use ($project): void {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            if ($locked->archived_at === null) {
                $locked->forceFill(['archived_at' => now()])->save();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project archived.')]);

        return to_route('workspaces.index');
    }

    public function restore(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        abort_unless($project->workspace_id === $workspace->id, 403);
        Gate::forUser($request->user())->authorize('archive', $project);

        DB::transaction(function () use ($project): void {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            if ($locked->archived_at !== null) {
                $locked->forceFill(['archived_at' => null])->save();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project restored.')]);

        return to_route('workspaces.index');
    }

}
