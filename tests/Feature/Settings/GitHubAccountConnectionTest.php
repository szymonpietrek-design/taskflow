<?php

namespace Tests\Feature\Settings;

use App\Models\GitHubAccount;
use App\Models\GitHubAppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubAccountConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_cannot_begin_callback_or_disconnect(): void
    {
        $this->get(route('github.account.connect'))->assertRedirect(route('login'));
        $this->get(route('github.account.callback'))->assertRedirect(route('login'));
        $this->delete(route('github.account.disconnect'))->assertRedirect(route('login'));
    }

    public function test_unconfigured_app_does_not_start_oauth(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('github.account.connect'))
            ->assertRedirect(route('profile.edit'));
        $this->assertEmpty(session('github_oauth_pending'));
    }

    public function test_connect_redirects_to_github_and_sets_random_state_bound_to_user(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $this->actingAs($user);

        $this->get(route('github.account.connect'))
            ->assertRedirectContains('https://github.com/login/oauth/authorize?')
            ->assertSessionHas('github_oauth_pending.user_id', $user->id);

        $state = session('github_oauth_pending.state');
        $this->assertIsString($state);
        $this->assertSame(64, strlen($state));

        $this->get(route('github.account.connect'));
        $this->assertNotSame($state, session('github_oauth_pending.state'));
    }

    public function test_verified_github_identity_is_stored_without_token_or_secret(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $state = $this->begin($user);
        $this->fakeValidGitHubResponse();

        $this->get(route('github.account.callback', ['state' => $state, 'code' => 'temporary-oauth-code']))
            ->assertRedirect(route('profile.edit'));

        $account = $user->githubAccount()->firstOrFail();
        $this->assertSame(8675309, $account->github_user_id);
        $this->assertSame('octocat', $account->github_login);
        $this->assertSame('https://avatars.githubusercontent.com/u/8675309?v=4', $account->avatar_url);
        $this->assertNotNull($account->connected_at);
        $this->assertArrayNotHasKey('access_token', $account->getAttributes());
        $this->assertStringNotContainsString('ghu_super-secret-token', json_encode(DB::table('github_accounts')->first()));
        $this->assertNull(session('github_oauth_pending'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://github.com/login/oauth/access_token'
            && $request['code'] === 'temporary-oauth-code'
            && $request['client_id'] === 'Iv1.test-client-id'
            && $request['client_secret'] === 'very-secret'
            && $request['redirect_uri'] === route('github.account.callback'));
    }

    public function test_mismatched_and_missing_states_are_rejected_before_github_requests(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $this->begin($user);
        Http::preventStrayRequests();

        $this->get(route('github.account.callback', ['state' => 'wrong', 'code' => 'code']))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('github_accounts', 0);
        $this->assertNull(session('github_oauth_pending'));

        $this->get(route('github.account.callback', ['state' => 'wrong', 'code' => 'code']))
            ->assertRedirect(route('profile.edit'));
        Http::assertNothingSent();
    }

    public function test_state_expires_after_ten_minutes(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $state = $this->begin($user);
        $this->travel(11)->minutes();
        Http::preventStrayRequests();

        $this->get(route('github.account.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('profile.edit'));
        Http::assertNothingSent();
        $this->assertDatabaseCount('github_accounts', 0);
    }

    public function test_callback_must_be_completed_by_user_who_started_flow(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->configureOAuth();
        $state = $this->begin($first);
        $this->actingAs($second);
        Http::preventStrayRequests();

        $this->get(route('github.account.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('github_accounts', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_or_denied_callback_does_not_exchange_code(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $state = $this->begin($user);
        Http::preventStrayRequests();

        $this->get(route('github.account.callback', ['state' => $state, 'error' => 'access_denied']))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('github_accounts', 0);
        Http::assertNothingSent();
    }

    public function test_github_oauth_error_does_not_create_links(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $state = $this->begin($user);
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['error' => 'bad_verification_code'], 200),
        ]);
        $this->get(route('github.account.callback', ['state' => $state, 'code' => 'bad-code']))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('github_accounts', 0);
    }

    public function test_invalid_github_identity_does_not_create_links(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $state = $this->begin($user);
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_token'], 200),
            'https://api.github.com/user' => Http::response(['id' => 0, 'login' => 'invalid'], 200),
        ]);
        $this->get(route('github.account.callback', ['state' => $state, 'code' => 'new-code']))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('github_accounts', 0);
    }

    public function test_same_github_identity_cannot_be_linked_to_two_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->configureOAuth();
        GitHubAccount::query()->create([
            'user_id' => $owner->id,
            'github_user_id' => 8675309,
            'github_login' => 'octocat',
            'connected_at' => now(),
        ]);
        $state = $this->begin($other);
        $this->fakeValidGitHubResponse();

        $this->get(route('github.account.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('github_accounts', 1);
        $this->assertNull($other->githubAccount()->first());
    }

    public function test_already_connected_user_cannot_replace_account_silently(): void
    {
        $user = User::factory()->create();
        $this->configureOAuth();
        $user->githubAccount()->create([
            'github_user_id' => 123456,
            'github_login' => 'old-user',
            'connected_at' => now(),
        ]);
        $this->actingAs($user)->get(route('github.account.connect'))
            ->assertRedirect(route('profile.edit'));
        $this->assertNull(session('github_oauth_pending'));
    }

    public function test_disconnect_only_deletes_current_users_link(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$user, $other] as $i => $person) {
            $person->githubAccount()->create([
                'github_user_id' => 100 + $i,
                'github_login' => 'test-'.$i,
                'connected_at' => now(),
            ]);
        }

        $this->actingAs($user)->delete(route('github.account.disconnect'))
            ->assertRedirect(route('profile.edit'));
        $this->assertNull($user->githubAccount()->first());
        $this->assertNotNull($other->githubAccount()->first());
    }

    private function configureOAuth(): void
    {
        GitHubAppSetting::query()->create([
            'id' => 1,
            'app_id' => 123,
            'private_key' => 'fixture-only-for-user-oauth',
            'client_id' => 'Iv1.test-client-id',
            'client_secret' => 'very-secret',
        ]);
    }

    private function begin(User $user): string
    {
        $this->actingAs($user)->get(route('github.account.connect'))
            ->assertRedirectContains('https://github.com/login/oauth/authorize?');

        return session('github_oauth_pending.state');
    }

    private function fakeValidGitHubResponse(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'ghu_super-secret-token',
                'token_type' => 'bearer',
            ], 200),
            'https://api.github.com/user' => Http::response([
                'id' => 8675309,
                'login' => 'octocat',
                'type' => 'User',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/8675309?v=4',
            ], 200),
        ]);
    }
}
