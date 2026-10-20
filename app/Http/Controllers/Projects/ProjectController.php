<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\WorkspaceMember;
use App\Support\Projects\ProjectLogo;
use App\Models\Task;
use App\Support\Projects\ProjectOverview;
use App\Support\Tasks\TaskCreationOptions;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
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
            ProjectVisibility::PRIVATE,
            $validated['description'] ?? null,
            $validated['icon'] ?? null,
            $validated['color'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Project created.'),
        ]);

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project, ProjectOverview $overview, TaskCreationOptions $taskCreationOptions): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);

        $request->session()->put('current_workspace_id', $project->workspace_id);

        $canCreateTask = $request->user()->can('create', [Task::class, $project]);

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'key' => $project->key,
                'description' => $project->description,
                'icon' => $project->icon,
                'color' => $project->color,
                'logo_url' => ProjectLogo::url($project),
                'type' => strtolower($project->type->name),
                'visibility' => strtolower($project->visibility->name),
                'can_manage' => $request->user()->can('update', $project),
                'is_archived' => $project->archived_at !== null,
            ],
            'can_create_task' => $canCreateTask,
            'can_manage_team' => $request->user()->can('manageMembers', $project),
            'can_manage_managers' => $request->user()->can('manageManagers', $project),
            'task_form' => $canCreateTask ? $taskCreationOptions->forProject($project) : null,
            'overview' => $overview->forProject($project),
            'team_preview' => $this->teamPreview($project),
        ]);
    }

    /**
     * Preview effective project access (inherited admins + direct members),
     * with a bounded list of additional people for the +N tooltip.
     *
     * @return array{members: list<array{id: int, name: string, role: string}>, overflow_members: list<array{id: int, name: string, role: string}>, remaining: int, unlisted_count: int, total: int}
     */
    private function teamPreview(Project $project): array
    {
        $adminRoles = [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value];
        $previewLimit = 8;
        $overflowLimit = 6;
        $listLimit = $previewLimit + $overflowLimit;

        $administrators = $project->workspace->memberships()
            ->whereIn('role', $adminRoles)
            ->whereNull('suspended_at')
            ->whereHas('user', fn (Builder $query) => $query->whereNull('disabled_at'));

        // A suspended administrator must never regain access through an old
        // project membership. Exclude all admin grants, including suspended.
        $members = $project->memberships()
            ->whereNotIn('user_id', WorkspaceMember::query()
                ->select('user_id')
                ->where('workspace_id', $project->workspace_id)
                ->whereIn('role', $adminRoles))
            ->whereHas('user', fn (Builder $query) => $query->whereNull('disabled_at'));

        $total = (clone $administrators)->count() + (clone $members)->count();

        $administratorPreview = (clone $administrators)
            ->with('user:id,name')
            ->orderBy('role')
            ->orderBy('id')
            ->limit($listLimit)
            ->get()
            ->map(fn (WorkspaceMember $member): array => [
                'id' => $member->user_id,
                'name' => $member->user->name,
                'role' => $member->role === WorkspaceRole::OWNER ? 'owner' : 'admin',
            ]);

        $remainingSlots = $listLimit - $administratorPreview->count();
        $projectPreview = $remainingSlots > 0
            ? (clone $members)
                ->with('user:id,name')
                ->orderBy('id')
                ->limit($remainingSlots)
                ->get()
                ->map(fn (ProjectMember $member): array => [
                    'id' => $member->user_id,
                    'name' => $member->user->name,
                    'role' => $member->role === ProjectRole::MANAGER ? 'manager' : 'member',
                ])
            : collect();

        $people = $administratorPreview->concat($projectPreview)->values();

        return [
            'members' => $people->take($previewLimit)->values()->all(),
            'overflow_members' => $people->slice($previewLimit)->values()->all(),
            'remaining' => max(0, $total - $previewLimit),
            'unlisted_count' => max(0, $total - $people->count()),
            'total' => $total,
        ];
    }

    private function currentWorkspace(Request $request): ?Workspace
    {
        $currentWorkspace = app(WorkspaceContext::class)->resolve($request)['current'];

        if ($currentWorkspace === null || $request->user() === null) {
            return null;
        }

        return Workspace::find($currentWorkspace['id']);
    }
}
