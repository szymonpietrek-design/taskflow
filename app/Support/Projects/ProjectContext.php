<?php

namespace App\Support\Projects;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Support\Projects\ProjectLogo;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ProjectContext
{
    public function __construct(private readonly WorkspaceContext $workspaceContext) {}

    /**
     * @return array{
     *     items: array<int, array{id: int, name: string, slug: string, key: string, type: string, visibility: string, icon: string|null, color: string|null, logo_url: string|null}>,
     *     can_create: bool
     * }
     */
    public function resolve(Request $request): array
    {
        $user = $request->user();
        $currentWorkspace = $this->workspaceContext->resolve($request)['current'];

        if ($user === null || $currentWorkspace === null) {
            return [
                'items' => [],
                'can_create' => false,
            ];
        }

        $workspaceId = $currentWorkspace['id'];
        $workspaceMembership = $user->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->whereNull('suspended_at')
            ->first();

        $query = Project::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('archived_at');

        if (! in_array($workspaceMembership?->role, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)) {
            $query->whereHas('memberships', fn (Builder $memberships) => $memberships
                ->where('user_id', $user->id));
        }

        $items = $query
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'key' => $project->key,
                'type' => strtolower($project->type->name),
                'visibility' => strtolower($project->visibility->name),
                'icon' => $project->icon,
                'color' => $project->color,
                'logo_url' => ProjectLogo::url($project),
            ])
            ->values()
            ->all();

        $workspace = \App\Models\Workspace::find($workspaceId);

        return [
            'items' => $items,
            'can_create' => $workspace !== null
                && $user->can('create', [Project::class, $workspace]),
        ];
    }
}
