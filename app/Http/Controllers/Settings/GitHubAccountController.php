<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Integrations\GitHub\GitHubUserIdentityClient;
use App\Models\GitHubAccount;
use App\Models\GitHubAppSetting;
use App\Models\UserVcsAccount;
use App\Integrations\Vcs\Providers\GitHubInstance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class GitHubAccountController extends Controller
{
    private const SESSION_KEY = 'github_oauth_pending';
    private const STATE_TTL_SECONDS = 600;

    public function connect(Request $request): RedirectResponse
    {
        $settings = GitHubAppSetting::current();
        if (! $settings?->userAuthorizationConfigured()) {
            return $this->feedback('GitHub user authorization is not configured.', 'error');
        }

        if ($request->user()->githubAccount()->exists()
            || UserVcsAccount::query()->where('user_id', $request->user()->id)
                ->where('vcs_instance_id', GitHubInstance::get()->id)->exists()) {
            return $this->feedback('Disconnect the current GitHub account before linking another.', 'error');
        }

        $state = bin2hex(random_bytes(32));
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $request->user()->id,
            'state' => $state,
            'started_at' => now()->timestamp,
        ]);

        // GitHub App user-to-server OAuth (not a second GitHub App or repository installation).
        $authorizeUrl = 'https://github.com/login/oauth/authorize?'.http_build_query([
            'client_id' => $settings->client_id,
            'redirect_uri' => route('github.account.callback'),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($authorizeUrl);
    }

    public function callback(Request $request, GitHubUserIdentityClient $github): RedirectResponse
    {
        // Pull once: repeated/replayed callbacks never reuse an OAuth state.
        $pending = $request->session()->pull(self::SESSION_KEY);
        $state = $request->query('state');
        if (! is_array($pending) || ! is_string($state)
            || ! is_string($pending['state'] ?? null)
            || ! hash_equals($pending['state'], $state)
            || ($pending['user_id'] ?? null) !== $request->user()->id
            || ! is_int($pending['started_at'] ?? null)
            || now()->timestamp < $pending['started_at']
            || now()->timestamp - $pending['started_at'] > self::STATE_TTL_SECONDS) {
            return $this->feedback('GitHub authorization expired or is invalid. Try again.', 'error');
        }

        if ($request->has('error')) {
            return $this->feedback('GitHub authorization was canceled or denied.', 'error');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '' || strlen($code) > 1024) {
            return $this->feedback('GitHub did not return a valid authorization code.', 'error');
        }

        $settings = GitHubAppSetting::current();
        if (! $settings?->userAuthorizationConfigured()) {
            return $this->feedback('GitHub user authorization is not configured.', 'error');
        }

        if ($request->user()->githubAccount()->exists()
            || UserVcsAccount::query()->where('user_id', $request->user()->id)
                ->where('vcs_instance_id', GitHubInstance::get()->id)->exists()) {
            return $this->feedback('Disconnect the current GitHub account before linking another.', 'error');
        }

        try {
            $identity = $github->verifyAuthorizationCode($settings, $code, route('github.account.callback'));
        } catch (ConnectionException|RuntimeException $e) {
            // Avoid leaking credentials, code, token or internal GitHub responses to the UI/logs.
            return $this->feedback('Could not verify your GitHub account. Try again.', 'error');
        }

        try {
            $linked = DB::transaction(function () use ($request, $identity): bool {
                // Database uniqueness constraints enforce these invariants under concurrent callbacks.
                if (GitHubAccount::query()->where('github_user_id', $identity['github_user_id'])->exists()
                    || $request->user()->githubAccount()->exists()) {
                    return false;
                }

                $legacy = $request->user()->githubAccount()->create([
                    ...$identity,
                    'connected_at' => now(),
                ]);
                UserVcsAccount::query()->create([
                    'user_id' => $request->user()->id,
                    'vcs_instance_id' => GitHubInstance::get()->id,
                    'external_user_id' => (string) $identity['github_user_id'],
                    'login' => $identity['github_login'],
                    'avatar_url' => $identity['avatar_url'],
                    'connected_at' => $legacy->connected_at,
                    'legacy_github_account_id' => $legacy->id,
                ]);

                return true;
            });
        } catch (UniqueConstraintViolationException $e) {
            $linked = false;
        }

        if (! $linked) {
            return $this->feedback('This GitHub account is already linked or your profile has a linked account.', 'error');
        }

        return $this->feedback('GitHub account connected.', 'success');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        // Delete only this user's verified link, not imported GitHub commits or installations.
        DB::transaction(function () use ($request): void {
            UserVcsAccount::query()->where('user_id', $request->user()->id)
                ->where('vcs_instance_id', GitHubInstance::get()->id)->delete();
            $request->user()->githubAccount()->delete();
        });

        return $this->feedback('GitHub account disconnected.', 'success');
    }

    private function feedback(string $message, string $type): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => __($message)]);

        return to_route('profile.edit');
    }
}
