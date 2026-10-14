<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_settings_page_is_displayed(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('language.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Language')
                ->where('selectedLocale', 'en')
                ->has('locales', 2));
    }

    public function test_user_can_change_interface_language_to_polish(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->patch(route('language.update'), ['locale' => 'pl'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('language.edit'));

        $this->assertSame('pl', $user->refresh()->locale);
    }

    public function test_unsupported_interface_language_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->from(route('language.edit'))
            ->patch(route('language.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale')
            ->assertRedirect(route('language.edit'));

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_authenticated_requests_use_saved_interface_locale(): void
    {
        $user = User::factory()->create(['locale' => 'pl']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'pl'));
    }
}
