<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceMemberRequest;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
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
                    && ($currentMembership?->role === WorkspaceRole::OWNER
                        || $membership->role === WorkspaceRole::MEMBER),
                'is_you' => $membership->user_id === $request->user()->id,
            ]),
        ]);
    }

    public function store(StoreWorkspaceMemberRequest $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No TaskFlow account was found with this email address.',
            ]);
        }

        // The unique constraint on (workspace_id, user_id) also protects concurrent requests.
        $membership = WorkspaceMember::query()->createOrFirst(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            [
                'role' => $data['role'] === 'admin' ? WorkspaceRole::ADMIN : WorkspaceRole::MEMBER,
                'joined_at' => now(),
            ],
        );

        if (! $membership->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'email' => 'This user already belongs to this workspace.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Member added to workspace.',
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

        DB::transaction(function () use ($workspace, $member): void {
            $target = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            // Clear only project grants in this workspace; keep tasks, worklogs and messages.
            ProjectMember::query()
                ->where('user_id', $target->user_id)
                ->whereHas('project', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->delete();
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
                || ($actor->role !== WorkspaceRole::OWNER && $member->role !== WorkspaceRole::MEMBER),
            403,
        );
    }
}
