<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\I18n\LanguageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LanguageFilePacksTest extends TestCase
{
    use RefreshDatabase;

    private string $languageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->languageDir = sys_get_temp_dir().'/taskflow-language-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->languageDir);
        $this->write('en', ['_nativeName' => 'English', 'home.title' => 'Dashboard']);
        $this->write('pl', ['_nativeName' => 'Polski', 'home.title' => 'Pulpit']);
        $this->app->instance(LanguageCatalog::class, new LanguageCatalog($this->languageDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->languageDir);
        parent::tearDown();
    }

    public function test_language_file_is_detected_and_served_without_registration(): void
    {
        $this->write('de', ['_nativeName' => 'Deutsch', 'home.title' => 'Übersicht']);

        $this->get('/ui-translations/de')->assertOk()->assertExactJson([
            '_nativeName' => 'Deutsch',
            'home.title' => 'Übersicht',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/settings/language')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Language')
                ->where('locales.0.value', 'de') // sorted alphabetically: de, en, pl
                ->etc());

        $this->actingAs($user)->patch('/settings/language', ['locale' => 'de'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'de']);
    }

    public function test_guest_language_selection_uses_files_and_rejects_unknown_locale(): void
    {
        $this->write('de', ['_nativeName' => 'Deutsch', 'home.title' => 'Übersicht']);

        $this->post('/ui-locale', ['locale' => 'de'])->assertRedirect()->assertCookie('locale', 'de');
        $this->post('/ui-locale', ['locale' => 'ru'])->assertSessionHasErrors('locale');
        $this->get('/ui-translations/ru')->assertNotFound();
    }

    public function test_removed_or_invalid_pack_cannot_be_selected_and_stored_locale_falls_back(): void
    {
        $this->write('de', ['_nativeName' => 'Deutsch', 'home.title' => 'Übersicht']);
        $user = User::factory()->create(['locale' => 'de']);
        File::delete($this->languageDir.'/de.json');

        $this->actingAs($user)->get('/settings/language')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('selectedLocale', 'en')->etc());
        $this->actingAs($user)->patch('/settings/language', ['locale' => 'de'])->assertSessionHasErrors('locale');
        $this->get('/ui-translations/de')->assertNotFound();

        File::put($this->languageDir.'/de.json', '{bad json');
        $this->get('/ui-translations/de')->assertNotFound();
    }

    public function test_built_in_english_and_polish_have_the_same_translation_keys(): void
    {
        $english = json_decode(File::get(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $polish = json_decode(File::get(lang_path('pl.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($english), array_keys($polish));
        $this->assertArrayHasKey('dashboard.needsAttention', $english);
        $this->assertArrayHasKey('Language updated.', $polish);
    }

    public function test_built_in_languages_include_github_profile_and_guest_language_copy(): void
    {
        $english = json_decode(File::get(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $polish = json_decode(File::get(lang_path('pl.json')), true, 512, JSON_THROW_ON_ERROR);

        $keys = [
            'profile.github.title',
            'profile.github.description',
            'profile.github.avatarAlt',
            'profile.github.verified',
            'profile.github.connectedOn',
            'profile.github.openProfile',
            'profile.github.disconnect',
            'profile.github.notConnected',
            'profile.github.optional',
            'profile.github.connect',
            'profile.github.verifyEmail',
            'profile.github.unavailable',
            'profile.github.confirmTitle',
            'profile.github.confirmDescription',
            'language.guestNote',
        ];

        foreach ([$english, $polish] as $dictionary) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $dictionary);
                $this->assertNotSame('', trim($dictionary[$key]));
            }
        }
    }

    public function test_unsafe_paths_and_non_string_values_are_not_valid_packs(): void
    {
        $catalog = app(LanguageCatalog::class);
        $this->assertNull($catalog->read('../.env'));
        $this->assertNull($catalog->read('de/../../en'));
        $this->write('de', ['_nativeName' => 'Deutsch', 'bad' => ['nested' => true]]);
        $this->assertFalse($catalog->has('de'));
        $this->get('/ui-translations/de')->assertNotFound();
    }

    private function write(string $locale, array $messages): void
    {
        File::put($this->languageDir.'/'.$locale.'.json', json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
