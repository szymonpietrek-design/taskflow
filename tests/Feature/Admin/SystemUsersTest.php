<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableJson as Assert;
use Tests\TestCase;

class SystemUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->is_system_admin = true;
        $admin->save();
        return $admin;
    }

    public function test_public_registration_routes_are_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Guest', 'email' => 'guest@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'guest@example.com']);
    }

    public function test_regular_users_and_workspace_owners_cannot_access_global_users(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->get('/admin/users')->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', [
            'name' => 'New', 'email' => 'new@example.com', 'locale' => 'pl',
            'password' => 'temporarypassword', 'password_confirmation' => 'temporarypassword',
            'is_system_admin' => true,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_workspace_owner_cannot_change_other_accounts_or_system_roles(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $target = User::factory()->create();
        $this->actingAs($owner)->patch("/admin/users/{$target->id}", [
            'name' => 'Hijacked', 'email' => 'other@example.com', 'locale' => 'pl',
        ])->assertForbidden();
        $this->patch("/admin/users/{$target->id}/role", ['is_system_admin' => true])->assertForbidden();
        $this->patch("/admin/users/{$target->id}/status", ['disabled' => true])->assertForbidden();
        $this->put("/admin/users/{$target->id}/password", [
            'password' => 'unauthorized-password', 'password_confirmation' => 'unauthorized-password',
        ])->assertForbidden();
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertFalse((bool) $target->fresh()->is_system_admin);
    }

    public function test_admin_can_list_search_and_filter_users(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Anna Example', 'email' => 'anna@example.com']);
        $disabledUser = User::factory()->create(['name' => 'Hidden Disabled']);
        $disabledUser->disabled_at = now();
        $disabledUser->save();

        $this->actingAs($admin)->get('/admin/users?q=anna&status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/Users')
                ->where('users.total', 1)
                ->where('users.data.0.email', 'anna@example.com')
                ->where('filters.status', 'active')
                ->etc());
    }

    public function test_admin_can_create_user_without_granting_workspace_access(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => '  Jan Example ', 'email' => 'JAN@Example.com', 'locale' => 'pl',
            'password' => 'secure-temporary-password',
            'password_confirmation' => 'secure-temporary-password',
            'is_system_admin' => false,
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'jan@example.com')->sole();
        $this->assertSame('Jan Example', $user->name);
        $this->assertFalse($user->is_system_admin);
        $this->assertTrue(Hash::check('secure-temporary-password', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(0, $user->workspaceMemberships()->count());
    }

    public function test_create_rejects_duplicate_email_and_weak_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Duplicate', 'email' => strtoupper($admin->email), 'locale' => 'en',
            'password' => 'short', 'password_confirmation' => 'short', 'is_system_admin' => false,
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_administrator_passwords_allow_six_characters_but_reject_five(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Short Password', 'email' => 'short@example.com', 'locale' => 'pl',
            'password' => 'A1b!xy', 'password_confirmation' => 'A1b!xy',
            'is_system_admin' => false,
        ])->assertSessionHasNoErrors();

        $target = User::query()->where('email', 'short@example.com')->sole();
        $this->assertTrue(Hash::check('A1b!xy', $target->password));

        $this->put("/admin/users/{$target->id}/password", [
            'password' => 'abcde', 'password_confirmation' => 'abcde',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('A1b!xy', $target->fresh()->password));

        $this->put("/admin/users/{$target->id}/password", [
            'password' => 'xyZ!34', 'password_confirmation' => 'xyZ!34',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('xyZ!34', $target->fresh()->password));

        $this->post('/admin/users', [
            'name' => 'Too Short', 'email' => 'too-short@example.com', 'locale' => 'pl',
            'password' => 'A1b!x', 'password_confirmation' => 'A1b!x',
            'is_system_admin' => false,
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'too-short@example.com']);
    }

    public function test_admin_can_update_profile_and_reset_password_without_changing_workspace_membership(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->patch("/admin/users/{$target->id}", [
            'name' => 'New name', 'email' => 'updated@example.com', 'locale' => 'pl',
        ])->assertSessionHasNoErrors();
        $this->put("/admin/users/{$target->id}/password", [
            'password' => 'another-secure-password', 'password_confirmation' => 'another-secure-password',
        ])->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertSame('New name', $target->name);
        $this->assertSame('updated@example.com', $target->email);
        $this->assertTrue(Hash::check('another-secure-password', $target->password));
    }

    public function test_disabled_accounts_cannot_log_in_or_keep_authenticated_sessions(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->patch("/admin/users/{$target->id}/status", ['disabled' => true])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($target->fresh()->disabled_at);

        $this->post('/logout');
        $this->post('/login', ['email' => $target->email, 'password' => 'password']);
        $this->assertGuest();

        $this->actingAs($target)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_reenable_account_and_existing_password_works(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->disabled_at = now();
        $target->save();
        $this->actingAs($admin)->patch("/admin/users/{$target->id}/status", ['disabled' => false])
            ->assertSessionHasNoErrors();
        $this->assertNull($target->fresh()->disabled_at);
        $this->post('/logout');
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_admin_can_assign_and_revoke_system_administrator_role(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->patch("/admin/users/{$target->id}/role", ['is_system_admin' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($target->fresh()->is_system_admin);
        $this->patch("/admin/users/{$target->id}/role", ['is_system_admin' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_system_admin);
    }

    public function test_cannot_disable_self_or_demote_self(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->patch("/admin/users/{$admin->id}/status", ['disabled' => true])
            ->assertSessionHasErrors('disabled');
        $this->patch("/admin/users/{$admin->id}/role", ['is_system_admin' => false])
            ->assertSessionHasErrors('is_system_admin');
        $this->assertTrue($admin->fresh()->is_system_admin);
        $this->assertNull($admin->fresh()->disabled_at);
    }

    public function test_console_can_grant_access_to_existing_user_without_a_default_password(): void
    {
        $user = User::factory()->create();
        $this->artisan('users:system-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_system_admin);
        $this->artisan('users:system-admin', ['email' => $user->email, '--revoke' => true])->assertFailed();
        $this->assertTrue($user->fresh()->is_system_admin);
    }
}
