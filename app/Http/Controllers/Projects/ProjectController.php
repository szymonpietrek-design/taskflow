<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Models\Project;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($this->currentWorkspace($request) === null) {
            return to_route('workspaces.index');
        }

        return Inertia::render('projects/Index');
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);

        abort_if($workspace === null, 404);

        $validated = $request->validated();

        $project = $createProject->handle(
            $request->user(),
            $workspace,
            $validated['name'],
            $validated['key'],
            ProjectType::from((int) $validated['type']),
            ProjectVisibility::from((int) $validated['visibility']),
            $validated['description'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Project created.'),
        ]);

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);

        $request->session()->put('current_workspace_id', $project->workspace_id);

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'key' => $project->key,
                'description' => $project->description,
                'type' => strtolower($project->type->name),
                'visibility' => strtolower($project->visibility->name),
                'can_manage' => $request->user()->can('update', $project),
            ],
        ]);
    }

    private function currentWorkspace(Request $request): ?Workspace
    {
        $currentWorkspace = app(WorkspaceContext::class)->resolve($request)['current'];

        if ($currentWorkspace === null || $request->user() === null) {
            return null;
        }

        return $request->user()->workspaces()
            ->whereKey($currentWorkspace['id'])
            ->first();
    }
}
