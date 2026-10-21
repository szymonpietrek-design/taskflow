<?php

namespace Tests\Feature\Admin;

use App\Models\GitHubAppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GitHubAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Inertia feature tests should not depend on a prebuilt Vite manifest.
        $this->withoutVite();
    }

    public function test_only_system_admin_can_view_or_change_global_github_app_settings(): void
    {
        $member = User::factory()->create(['is_system_admin' => false]);
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($member)->get(route('admin.github.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.github.store'), ['app_id' => 987])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.github.index'))->assertOk();
    }

    public function test_admin_can_upload_rsa_key_encrypted_and_it_is_not_in_inertia_props(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $pem = $this->rsaPrivateKey();
        $this->actingAs($admin)->post(route('admin.github.store'), [
            'app_id' => 1234,
            'private_key' => UploadedFile::fake()->createWithContent('github-app.pem', $pem),
        ])->assertRedirect(route('admin.github.index'));

        $this->assertDatabaseCount('github_app_settings', 1);
        $settings = GitHubAppSetting::current();
        $this->assertNotNull($settings);
        $this->assertSame(1234, $settings->app_id);
        $this->assertSame($pem, $settings->private_key);
        $this->assertSame($admin->id, $settings->updated_by);
        $cipher = DB::table('github_app_settings')->value('private_key');
        $this->assertNotEquals($pem, $cipher);
        $this->assertStringNotContainsString('PRIVATE KEY', $cipher);
        $this->assertArrayNotHasKey('private_key', $settings->toArray());
        $this->actingAs($admin)->get(route('admin.github.index'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/GitHub')
            ->where('settings.app_id', '1234')
            ->where('settings.has_private_key', true)
            ->missing('settings.private_key')
            ->etc());
    }

    public function test_updating_app_id_without_new_file_keeps_existing_private_key(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $pem = $this->rsaPrivateKey();
        GitHubAppSetting::query()->create(['id' => 1, 'app_id' => 1234, 'private_key' => $pem]);
        $this->actingAs($admin)->post(route('admin.github.store'), ['app_id' => 5678])
            ->assertRedirect(route('admin.github.index'));
        $this->assertSame(5678, GitHubAppSetting::current()?->app_id);
        $this->assertSame($pem, GitHubAppSetting::current()?->private_key);
    }

    public function test_initial_save_requires_a_private_key_and_rejects_invalid_pem(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->from(route('admin.github.index'))
            ->post(route('admin.github.store'), ['app_id' => 1234])
            ->assertSessionHasErrors('private_key');

        $this->actingAs($admin)->from(route('admin.github.index'))
            ->post(route('admin.github.store'), [
                'app_id' => 1234,
                'private_key' => UploadedFile::fake()->createWithContent('github.pem', 'not-a-private-key'),
            ])->assertSessionHasErrors('private_key');
        $this->assertDatabaseCount('github_app_settings', 0);
    }

    private function rsaPrivateKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $pem));

        return $pem;
    }
}
