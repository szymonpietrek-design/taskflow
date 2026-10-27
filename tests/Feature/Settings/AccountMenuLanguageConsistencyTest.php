<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMenuLanguageConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_language_change_persists_and_keeps_current_page(): void
    {
        $user = User::factory()->create(['locale' => 'pl']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('language.update'), ['locale' => 'en', 'from_menu' => true])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertCookie('locale', 'en');

        $this->assertSame('en', $user->refresh()->locale);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale', 'en')->etc());
    }

    public function test_public_dictionaries_match_the_actual_interface_language(): void
    {
        $polish = $this->get('/ui-translations/pl')->assertOk()->json();
        $english = $this->get('/ui-translations/en')->assertOk()->json();

        $this->assertSame('Wyloguj się', $polish['nav.logout']);
        $this->assertSame('Log out', $english['nav.logout']);
        $this->assertSame('Polski', $polish['_nativeName']);
        $this->assertSame('English', $english['_nativeName']);
    }
    public function test_json_language_switch_persists_in_both_directions_and_dashboard_uses_saved_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->patchJson(route('language.update'), ['locale' => 'pl'])
            ->assertOk()
            ->assertJsonPath('locale', 'pl')
            ->assertJsonPath('accountLocale', 'pl')
            ->assertCookie('locale', 'pl');

        $this->assertSame('pl', $user->refresh()->locale);
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale', 'pl')->etc());

        $this->actingAs($user)
            ->patchJson(route('language.update'), ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('accountLocale', 'en');

        $this->assertSame('en', $user->refresh()->locale);
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale', 'en')->etc());
    }

    public function test_unknown_locale_is_rejected_before_account_is_updated(): void
    {
        $user = User::factory()->create(['locale' => 'pl']);
        $this->actingAs($user)
            ->patchJson(route('language.update'), ['locale' => 'zz'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
        $this->assertSame('pl', $user->refresh()->locale);
    }

}
