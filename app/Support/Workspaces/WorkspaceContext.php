<?php

namespace App\Support\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceContext
{
    /**
     * @return array{
     *     current: array{id: int, name: string, slug: string, role: string, is_current: bool}|null,
     *     items: array<int, array{id: int, name: string, slug: string, role: string, is_current: bool}>
     * }
     */
    public function resolve(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [
                'current' => null,
                'items' => [],
            ];
        }

        $workspaces = $user->workspaces()
            ->select(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
            ->orderBy('workspaces.name')
            ->get();

        $currentWorkspaceId = $this->resolveCurrentWorkspaceId($request, $workspaces);

        $items = $workspaces
            ->map(function ($workspace) use ($currentWorkspaceId): array {
                $role = WorkspaceRole::from((int) $workspace->pivot->getAttribute('role'));

                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'role' => strtolower($role->name),
                    'is_current' => $workspace->id === $currentWorkspaceId,
                ];
            })
            ->values();

        return [
            'current' => $items->firstWhere('is_current', true),
            'items' => $items->all(),
        ];
    }

    /**
     * @param  Collection<int, Workspace>  $workspaces
     */
    private function resolveCurrentWorkspaceId(Request $request, Collection $workspaces): ?int
    {
        if ($workspaces->isEmpty()) {
            $request->session()->forget('current_workspace_id');

            return null;
        }

        $storedWorkspaceId = $request->session()->get('current_workspace_id');
        $currentWorkspaceId = is_numeric($storedWorkspaceId) ? (int) $storedWorkspaceId : null;

        if ($currentWorkspaceId !== null && ! $workspaces->contains('id', $currentWorkspaceId)) {
            $currentWorkspaceId = null;
        }

        if ($currentWorkspaceId === null) {
            $currentWorkspaceId = (int) $workspaces->first()->id;
        }

        $request->session()->put('current_workspace_id', $currentWorkspaceId);

        return $currentWorkspaceId;
    }
}
