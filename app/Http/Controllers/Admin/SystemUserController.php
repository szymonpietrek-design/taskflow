<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\I18n\LanguageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SystemUserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        $status = in_array($request->query('status'), ['active', 'disabled'], true)
            ? $request->query('status')
            : 'all';

        $users = User::query()
            ->select(['id', 'name', 'email', 'locale', 'is_system_admin', 'disabled_at', 'created_at'])
            ->withCount('workspaceMemberships')
            ->when($search !== '', fn ($query) => $query->where(function ($userQuery) use ($search): void {
                $userQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->when($status === 'active', fn ($query) => $query->whereNull('disabled_at'))
            ->when($status === 'disabled', fn ($query) => $query->whereNotNull('disabled_at'))
            ->orderBy('name')->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => ['q' => $search, 'status' => $status],
            'currentUserId' => $request->user()->id,
            'locales' => app(LanguageCatalog::class)->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'locale' => ['required', Rule::in(array_column(app(LanguageCatalog::class)->options(), 'value'))],
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
            'is_system_admin' => ['required', 'boolean'],
        ]);

        $user = new User();
        $user->name = trim($data['name']);
        $user->email = $data['email'];
        $user->locale = $data['locale'];
        $user->password = $data['password'];
        // Provisioned by a trusted administrator; works even without outbound email configured.
        $user->email_verified_at = now();
        $user->is_system_admin = (bool) $data['is_system_admin'];
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User created.']);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', Rule::in(array_column(app(LanguageCatalog::class)->options(), 'value'))],
        ]);

        $user->name = trim($data['name']);
        if ($user->email !== $data['email']) {
            $user->email = $data['email'];
            $user->email_verified_at = now();
        }
        $user->locale = $data['locale'];
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return back();
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'confirmed', Password::min(6)]]);

        DB::transaction(function () use ($user, $data): void {
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password changed.']);

        return back();
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['disabled' => ['required', 'boolean']]);
        $disabled = (bool) $data['disabled'];

        DB::transaction(function () use ($request, $user, $disabled): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($disabled && $user->id === $request->user()->id) {
                throw ValidationException::withMessages(['disabled' => 'You cannot disable your own account.']);
            }
            if ($disabled && $user->is_system_admin && $user->disabled_at === null) {
                $this->assertAnotherActiveAdminExists();
            }
            if ($disabled && $user->disabled_at === null) {
                $user->disabled_at = now();
                $user->setRememberToken(Str::random(60));
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } elseif (! $disabled && $user->disabled_at !== null) {
                $user->disabled_at = null;
                $user->save();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User status updated.']);

        return back();
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['is_system_admin' => ['required', 'boolean']]);
        $makeAdmin = (bool) $data['is_system_admin'];

        DB::transaction(function () use ($request, $user, $makeAdmin): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $makeAdmin && $user->id === $request->user()->id) {
                throw ValidationException::withMessages(['is_system_admin' => 'You cannot remove your own administrator role.']);
            }
            if (! $makeAdmin && $user->is_system_admin && $user->disabled_at === null) {
                $this->assertAnotherActiveAdminExists();
            }
            $user->is_system_admin = $makeAdmin;
            $user->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'System role updated.']);

        return back();
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    private function assertAnotherActiveAdminExists(): void
    {
        if (User::query()->where('is_system_admin', true)->whereNull('disabled_at')->lockForUpdate()->get(['id'])->count() <= 1) {
            throw ValidationException::withMessages([
                'is_system_admin' => 'At least one active system administrator is required.',
            ]);
        }
    }
}
