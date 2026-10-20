<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        $canManage = $request->user()->can('manageMembers', $project);
        $canManageManagers = $request->user()->can('manageManagers', $project);

        $adminIds = $project->workspace->memberships()->whereNull('suspended_at')
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->pluck('user_id');
        $members = $project->memberships()->whereNotIn('user_id', $adminIds)
            ->with('user:id,name,email,disabled_at')
            ->orderBy('role')->orderBy('id')->get()
            ->map(fn (ProjectMember $member): array => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => strtolower($member->role->name),
                'is_disabled' => $member->user->disabled_at !== null,
                'can_manage' => $canManage && $member->user_id !== $request->user()->id
                    && ($canManageManagers || $member->role === ProjectRole::MEMBER),
            ])->all();

        $administrators = $project->workspace->memberships()
            ->whereNull('suspended_at')
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->with('user:id,name,email')
            ->orderBy('role')->get()
            ->map(fn ($member): array => [
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => strtolower($member->role->name),
            ])->all();

        return Inertia::render('projects/Members', [
            'project' => ['id' => $project->id, 'name' => $project->name, 'key' => $project->key, 'is_archived' => $project->archived_at !== null],
            'members' => $members,
            'administrators' => $administrators,
            'can_manage' => $canManage,
            'can_manage_managers' => $canManageManagers,
        ]);
    }

    public function candidates(Request $request, Project $project): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageMembers', $project);
        $query = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:100']]);
        $search = trim($query['q'] ?? '');
        $users = User::query()->select('id', 'name', 'email')
            ->whereNull('disabled_at')
            ->whereNotIn('id', ProjectMember::query()->select('user_id')->where('project_id', $project->id))
            ->whereNotIn('id', $project->workspace->memberships()
                ->select('user_id')->whereNull('suspended_at')
                ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]))
            ->when($search !== '', fn ($users) => $users->where(fn ($match) => $match
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->limit(13)->get();
        return response()->json(['users' => $users->take(12)->values(), 'has_more' => $users->count() > 12]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageMembers', $project);
        $isAdmin = $request->user()->can('manageManagers', $project);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::in($isAdmin ? ['member', 'manager'] : ['member'])],
        ]);
        $user = User::query()->whereKey($data['user_id'])->whereNull('disabled_at')->first();
        if ($user === null) {
            throw ValidationException::withMessages(['user_id' => __('Select an active user.')]);
        }
        $hasAdminAccess = $user->workspaceMemberships()
            ->where('workspace_id', $project->workspace_id)->whereNull('suspended_at')
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])->exists();
        if ($hasAdminAccess) {
            throw ValidationException::withMessages(['user_id' => __('This user already has workspace-wide access.')]);
        }
        $membership = ProjectMember::query()->createOrFirst(
            ['project_id' => $project->id, 'user_id' => $user->id],
            ['role' => $data['role'] === 'manager' ? ProjectRole::MANAGER : ProjectRole::MEMBER],
        );
        if (! $membership->wasRecentlyCreated) {
            throw ValidationException::withMessages(['user_id' => __('This user already belongs to this project.')]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project member added.')]);
        return back();
    }

    public function update(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        abort_unless($member->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        $data = $request->validate(['role' => ['required', Rule::in(['member', 'manager'])]]);
        abort_if($member->user_id === $request->user()->id, 403);
        $member->update(['role' => $data['role'] === 'manager' ? ProjectRole::MANAGER : ProjectRole::MEMBER]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project role updated.')]);
        return back();
    }

    public function destroy(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        abort_unless($member->project_id === $project->id, 404);
        Gate::forUser($request->user())->authorize('manageMembers', $project);
        abort_if($member->user_id === $request->user()->id, 403);
        if ($member->role === ProjectRole::MANAGER) {
            Gate::forUser($request->user())->authorize('manageManagers', $project);
        }
        // Keep tasks, discussions, worklogs and release history intact.
        DB::transaction(fn () => $member->delete());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project member removed.')]);
        return back();
    }
}
