<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMenuLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_change_from_account_menu_returns_to_current_page(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('language.update'), ['locale' => 'pl', 'from_menu' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertCookie('locale', 'pl');

        $this->assertSame('pl', $user->refresh()->locale);
    }

    public function test_language_change_without_menu_flag_uses_settings_page(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('language.update'), ['locale' => 'pl'])
            ->assertRedirect(route('language.edit'));
    }

    public function test_account_menu_only_offers_installed_languages(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locales.0.value', 'en')
                ->where('locales.1.value', 'pl')
                ->etc());
    }
}
