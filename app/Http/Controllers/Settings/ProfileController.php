<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GitHubAppSetting;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // Keep OAuth secrets and GitHub numeric IDs out of the Inertia page props.
            'githubAccount' => function () use ($request): ?array {
                $account = $request->user()->githubAccount()
                    ->first(['github_login', 'avatar_url', 'connected_at']);

                return $account ? [
                    'github_login' => $account->github_login,
                    'avatar_url' => $account->avatar_url,
                    'connected_at' => $account->connected_at?->toIso8601String(),
                ] : null;
            },
            'githubConnectionConfigured' => fn (): bool =>
                GitHubAppSetting::current()?->userAuthorizationConfigured() ?? false,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

}
