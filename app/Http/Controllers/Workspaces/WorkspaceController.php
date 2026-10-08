<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaces = $request->user()
            ->workspaces()
            ->select(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
            ->orderBy('workspaces.name')
            ->get();

        $storedWorkspaceId = $request->session()->get('current_workspace_id');
        $currentWorkspaceId = is_numeric($storedWorkspaceId) ? (int) $storedWorkspaceId : null;

        if ($currentWorkspaceId !== null && ! $workspaces->contains('id', $currentWorkspaceId)) {
            $request->session()->forget('current_workspace_id');
            $currentWorkspaceId = null;
        }

        $firstWorkspace = $workspaces->first();

        if ($currentWorkspaceId === null && $firstWorkspace !== null) {
            $currentWorkspaceId = $firstWorkspace->id;
            $request->session()->put('current_workspace_id', $currentWorkspaceId);
        }

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces
                ->map(function ($workspace) use ($currentWorkspaceId): array {
                    $pivot = $workspace->pivot;
                    $role = WorkspaceRole::from((int) $pivot->getAttribute('role'));

                    return [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'slug' => $workspace->slug,
                        'role' => strtolower($role->name),
                        'is_current' => $workspace->id === $currentWorkspaceId,
                    ];
                })
                ->values(),
        ]);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $workspace = $createWorkspace->handle(
            $request->user(),
            $request->string('name')->toString(),
        );

        $request->session()->put('current_workspace_id', $workspace->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Workspace created.'),
        ]);

        return to_route('workspaces.index');
    }
}
