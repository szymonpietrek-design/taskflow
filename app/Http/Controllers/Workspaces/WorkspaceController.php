<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use App\Support\Projects\ProjectLogo;
use App\Models\ProjectMember;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
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

        $adminWorkspaceIds = array_values(array_map(
            fn (array $item): int => $item['id'],
            array_filter($workspaceItems, fn (array $item): bool => in_array($item['role'], ['owner', 'admin'], true)),
        ));

        $managerProjectIds = ProjectMember::query()
            ->where('user_id', $request->user()->id)
            ->where('role', ProjectRole::MANAGER->value)
            ->pluck('project_id')
            ->all();

        // Members see only workspace-visible projects or their own private projects.
        // Owner/admin access is scoped to their own workspace, never global.
        $visibleProjects = Project::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where(function (Builder $query) use ($adminWorkspaceIds, $request): void {
                $query->whereIn('workspace_id', $adminWorkspaceIds)
                    ->orWhere('visibility', ProjectVisibility::WORKSPACE->value)
                    ->orWhereHas('memberships', fn (Builder $memberships) => $memberships
                        ->where('user_id', $request->user()->id));
            });

        $projects = (clone $visibleProjects)->whereNull('archived_at')->orderBy('name')->get();

        // Archived projects are accessible only to workspace administrators and their managers.
        // Regular workspace members must never discover private archived projects.
        $archivedProjects = (clone $visibleProjects)
            ->whereNotNull('archived_at')
            ->where(function (Builder $query) use ($adminWorkspaceIds, $managerProjectIds): void {
                $query->whereIn('workspace_id', $adminWorkspaceIds)
                    ->orWhereIn('id', $managerProjectIds);
            })
            ->orderBy('name')->get()
            ->filter(fn (Project $project): bool => $request->user()->can('archive', $project));

        $projectsByWorkspace = [];
        $archivedProjectsByWorkspace = [];
        foreach ($workspaceIds as $workspaceId) {
            $projectsByWorkspace[$workspaceId] = [];
            $archivedProjectsByWorkspace[$workspaceId] = [];
        }
        foreach ($projects as $project) {
            $projectsByWorkspace[$project->workspace_id][] = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'key' => $project->key,
                'type' => strtolower($project->type->name),
                'visibility' => strtolower($project->visibility->name),
                'icon' => $project->icon,
                'color' => $project->color,
                'logo_url' => ProjectLogo::url($project),
                'can_edit' => $request->user()->can('update', $project),
                'can_archive' => $request->user()->can('archive', $project),
                'is_archived' => false,
            ];
        }

        foreach ($archivedProjects as $project) {
            $archivedProjectsByWorkspace[$project->workspace_id][] = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'key' => $project->key,
                'type' => strtolower($project->type->name),
                'visibility' => strtolower($project->visibility->name),
                'icon' => $project->icon,
                'color' => $project->color,
                'logo_url' => ProjectLogo::url($project),
                'can_edit' => false,
                'can_archive' => $request->user()->can('archive', $project),
                'is_archived' => true,
            ];
        }

        return Inertia::render('workspaces/Index', [
            'memberPreviews' => $memberPreviews,
            'projectsByWorkspace' => $projectsByWorkspace,
            'archivedProjectsByWorkspace' => $archivedProjectsByWorkspace,
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

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        // The slug identifies the workspace and remains stable when its display name changes.
        $workspace->update([
            'name' => trim($request->validated('name')),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Workspace name updated.'),
        ]);

        return to_route('workspaces.index');
    }

}
