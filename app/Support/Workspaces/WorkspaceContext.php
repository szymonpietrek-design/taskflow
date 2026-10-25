<?php

namespace App\Support\Workspaces;

use App\Enums\WorkspaceRole;
use App\Support\Projects\ProjectVisibilityQuery;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class WorkspaceContext
{
    /** @return array{current: ?array, items: array} */
    public function resolve(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return ['current' => null, 'items' => []];
        }

        // A project collaborator may access the workspace via a project, without
        // any row in workspace_members. Only owner/admin hold a workspace role.
        $workspaces = Workspace::query()
            ->where(function ($query) use ($user): void {
                $query->whereHas('memberships', fn ($members) => $members
                    ->where('user_id', $user->id)
                    ->whereNull('suspended_at')
                    ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]))
                    ->orWhere(function ($memberAccess) use ($user): void {
                        // Only a genuinely readable project can grant derived workspace visibility.
                        // A regular member of an archived project must not see the workspace.
                        $memberAccess->whereHas('projects', fn ($projects) =>
                            ProjectVisibilityQuery::visibleTo($projects, $user->id));
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $currentId = $this->currentId($request, $workspaces);
        $adminRoles = $user->workspaceMemberships()
            ->whereIn('workspace_id', $workspaces->pluck('id'))
            ->whereNull('suspended_at')
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->get()
            ->keyBy('workspace_id');

        $items = $workspaces->map(function (Workspace $workspace) use ($currentId, $adminRoles): array {
            $role = $adminRoles->get($workspace->id)?->role;
            return [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                // "member" describes project-derived visibility, not a stored workspace role.
                'role' => $role !== null ? strtolower($role->name) : 'member',
                'is_current' => $workspace->id === $currentId,
            ];
        })->values()->all();

        return [
            'current' => collect($items)->firstWhere('is_current', true),
            'items' => $items,
        ];
    }

    /** @param Collection<int, Workspace> $workspaces */
    private function currentId(Request $request, Collection $workspaces): ?int
    {
        if ($workspaces->isEmpty()) {
            $request->session()->forget('current_workspace_id');
            return null;
        }
        $stored = $request->session()->get('current_workspace_id');
        $id = is_numeric($stored) ? (int) $stored : null;
        if ($id === null || ! $workspaces->contains('id', $id)) {
            $id = (int) $workspaces->first()->id;
        }
        $request->session()->put('current_workspace_id', $id);
        return $id;
    }
}
