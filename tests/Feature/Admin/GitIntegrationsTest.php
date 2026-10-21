<?php

namespace Tests\Feature\Admin;

use App\Integrations\GitHub\GitHubAppClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class GitIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_system_admin_can_open_global_git_integrations(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $member = User::factory()->create(['is_system_admin' => false]);

        $this->get(route('admin.git.index'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('admin.git.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.git.index'))->assertOk();
    }

    public function test_page_shows_all_providers_and_exposes_no_credentials(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $github = Mockery::mock(GitHubAppClient::class);
        $github->shouldReceive('configured')->once()->andReturn(true);
        $this->app->instance(GitHubAppClient::class, $github);

        $this->actingAs($admin)->get(route('admin.git.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Git')
                ->where('providers.0.key', 'github')
                ->where('providers.0.available', true)
                ->where('providers.0.configured', true)
                ->where('providers.1.key', 'gitlab')
                ->where('providers.1.available', false)
                ->where('providers.2.key', 'gitea')
                ->where('providers.2.available', false)
                ->missing('credentials')
                ->missing('private_key')
                ->missing('client_secret')
                ->etc());
    }

    public function test_unconfigured_github_still_available_and_configuration_page_remains(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->get(route('admin.git.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('providers.0.available', true)
                ->where('providers.0.configured', false)
                ->etc());
        $this->get(route('admin.github.index'))->assertOk();
    }
}
