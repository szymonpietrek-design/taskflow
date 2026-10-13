<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Models\WorkspaceMember;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceContext $workspaceContext): Response
    {
        $workspaceItems = $workspaceContext->resolve($request)['items'];
        $workspaceIds = array_column($workspaceItems, 'id');

        // Only expose active members of workspaces the authenticated user belongs to.
        // Keep this lookup page-specific rather than adding it to every Inertia response.
        $membersByWorkspace = WorkspaceMember::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereNull('suspended_at')
            ->with('user:id,name')
            ->orderBy('id')
            ->get(['id', 'workspace_id', 'user_id'])
            ->groupBy('workspace_id');

        $memberPreviews = [];
        foreach ($workspaceIds as $workspaceId) {
            $members = $membersByWorkspace->get($workspaceId, collect());

            $memberPreviews[$workspaceId] = [
                'members' => $members->take(3)->map(fn (WorkspaceMember $membership): array => [
                    'id' => $membership->user_id,
                    'name' => $membership->user->name,
                ])->values()->all(),
                'remaining' => max(0, $members->count() - 3),
            ];
        }

        return Inertia::render('workspaces/Index', [
            'memberPreviews' => $memberPreviews,
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
