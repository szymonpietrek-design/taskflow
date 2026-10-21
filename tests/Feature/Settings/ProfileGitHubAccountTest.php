<?php

namespace Tests\Feature\Settings;

use App\Models\GitHubAppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileGitHubAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_cannot_read_profile_github_link(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_unconnected_user_receives_no_account_and_unconfigured_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('githubAccount', null)
                ->where('githubConnectionConfigured', false)
                ->etc());
    }

    public function test_profile_only_exposes_safe_fields_from_the_current_users_github_link(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $account = $user->githubAccount()->create([
            'github_user_id' => 8675309,
            'github_login' => 'octocat',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/8675309?v=4',
            'connected_at' => now(),
        ]);
        $otherUser->githubAccount()->create([
            'github_user_id' => 123456,
            'github_login' => 'someone-else',
            'avatar_url' => null,
            'connected_at' => now(),
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('githubAccount.github_login', 'octocat')
                ->where('githubAccount.avatar_url', 'https://avatars.githubusercontent.com/u/8675309?v=4')
                ->where('githubAccount.connected_at', $account->connected_at->toIso8601String())
                ->missing('githubAccount.user_id')
                ->missing('githubAccount.github_user_id')
                ->missing('githubAccount.access_token')
                ->missing('githubAccount.client_secret')
                ->etc());

        $this->actingAs($otherUser)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('githubAccount.github_login', 'someone-else')
                ->etc());
    }

    public function test_connection_availability_is_a_boolean_and_does_not_expose_oauth_credentials(): void
    {
        $user = User::factory()->create();
        GitHubAppSetting::query()->create([
            'id' => 1,
            'app_id' => 12,
            'private_key' => 'fixture-key',
            'client_id' => 'Iv1.fixture-client',
            'client_secret' => 'fixture-secret',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('githubConnectionConfigured', true)
                ->missing('client_secret')
                ->missing('client_id')
                ->missing('private_key')
                ->etc());
    }

    public function test_disconnecting_updates_profile_without_deleting_another_users_link(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->githubAccount()->create([
            'github_user_id' => 8675309,
            'github_login' => 'octocat',
            'connected_at' => now(),
        ]);
        $otherUser->githubAccount()->create([
            'github_user_id' => 1111111,
            'github_login' => 'another',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('github.account.disconnect'))
            ->assertRedirect(route('profile.edit'));
        $this->assertDatabaseMissing('github_accounts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('github_accounts', ['user_id' => $otherUser->id]);
        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('githubAccount', null)
                ->etc());
    }
}
