<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceMemberRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::forUser($request->user())->authorize('manageMembers', $workspace);

        $search = trim($request->string('q')->toString());
        $search = mb_substr($search, 0, 120);

        $members = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->with('user:id,name,email')
            ->when($search !== '', fn ($query) => $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->where(function ($nameOrEmail) use ($search): void {
                    $nameOrEmail->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            }))
            ->orderBy('role')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $currentMembership = $request->user()->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->first();

        return Inertia::render('workspaces/Members', [
            'can_assign_admin' => $currentMembership?->role === WorkspaceRole::OWNER,
            'workspaceItem' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'filters' => ['q' => $search],
            'members' => $members->through(fn (WorkspaceMember $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => strtolower($membership->role->name),
                'joined_at' => $membership->joined_at?->toDateString(),
                'is_suspended' => $membership->suspended_at !== null,
                'can_manage' => $membership->role !== WorkspaceRole::OWNER
                    && $membership->user_id !== $request->user()->id
                    && $currentMembership?->role === WorkspaceRole::OWNER,
                'is_you' => $membership->user_id === $request->user()->id,
            ]),
        ]);
    }

    public function candidates(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageMembers', $workspace);
        abort_unless($request->user()->workspaceMemberships()->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')->where('role', WorkspaceRole::OWNER->value)->exists(), 403);

        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $search = trim($data['q'] ?? '');

        // This is a picker for existing accounts, not an account creation endpoint.
        // Exclude all memberships, including suspended ones, to avoid duplicates.
        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->whereNull('disabled_at')
            ->whereNotIn('id', WorkspaceMember::query()
                ->select('user_id')
                ->where('workspace_id', $workspace->id))
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search): void {
                $match->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(13)
            ->get();

        return response()->json([
            'users' => $users->take(12)->values(),
            'has_more' => $users->count() > 12,
        ]);
    }

    public function store(StoreWorkspaceMemberRequest $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($request->user()->workspaceMemberships()->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')->where('role', WorkspaceRole::OWNER->value)->exists(), 403);
        $user = User::query()->where('email', $data['email'])->whereNull('disabled_at')->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('No TaskFlow account was found with this email address.'),
            ]);
        }

        // Only owner/admin rows are allowed; project users are assigned within projects.
        // The unique constraint also protects concurrent requests.
        $membership = DB::transaction(function () use ($workspace, $user): WorkspaceMember {
            $membership = WorkspaceMember::query()->createOrFirst(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['role' => WorkspaceRole::ADMIN, 'joined_at' => now()],
            );
            if ($membership->wasRecentlyCreated) {
                // A workspace administrator inherits access and must not retain
                // stale project grants if they later lose this administrator role.
                \App\Models\ProjectMember::query()->where('user_id', $user->id)
                    ->whereHas('project', fn ($projects) => $projects->where('workspace_id', $workspace->id))
                    ->delete();
            }
            return $membership;
        });

        if (! $membership->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'email' => __('This user already belongs to this workspace.'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member added to workspace.'),
        ]);

        return back();
    }

    public function suspend(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->authorizeTarget($request, $workspace, $member);

        DB::transaction(function () use ($member): void {
            $target = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            if ($target->suspended_at === null) {
                $target->update(['suspended_at' => now()]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member access suspended.']);

        return back();
    }

    public function restore(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->authorizeTarget($request, $workspace, $member);

        DB::transaction(function () use ($member): void {
            $target = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            if ($target->suspended_at !== null) {
                $target->update(['suspended_at' => null]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member access restored.']);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->authorizeTarget($request, $workspace, $member);

        DB::transaction(function () use ($member): void {
            $target = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            // Revoking workspace administration does not remove explicit project grants.
            $target->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member removed from workspace.']);

        return back();
    }

    private function authorizeTarget(Request $request, Workspace $workspace, WorkspaceMember $member): void
    {
        Gate::forUser($request->user())->authorize('manageMembers', $workspace);
        abort_unless($member->workspace_id === $workspace->id, 404);

        $actor = $request->user()->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')
            ->firstOrFail();

        abort_if(
            $member->user_id === $request->user()->id
                || $member->role === WorkspaceRole::OWNER
                || $actor->role !== WorkspaceRole::OWNER,
            403,
        );
    }
}
