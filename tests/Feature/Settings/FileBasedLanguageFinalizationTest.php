<?php

namespace Tests\Feature\Settings;

use App\Support\I18n\LanguageCatalog;
use App\Support\Reports\ReportDocumentLocale;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FileBasedLanguageFinalizationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/taskflow-final-locale-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_file_based_i18n_has_no_obsolete_bundled_dictionaries(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/i18n/en.ts'));
        $this->assertFileDoesNotExist(resource_path('js/i18n/pl.ts'));
        $this->assertFileDoesNotExist(resource_path('js/pages/dashboardCopy.ts'));

        // Both the login and authenticated UI must use the same JSON endpoint.
        $loader = File::get(resource_path('js/i18n/index.ts'));
        $this->assertStringContainsString('/ui-translations/', $loader);
        $this->assertStringContainsString("loadLocale('en')", $loader);
        $this->assertStringContainsString('english?.[key]', $loader);

        foreach (File::allFiles(resource_path('js')) as $file) {
            if (! preg_match('/\.(?:ts|vue)$/', $file->getFilename())) {
                continue;
            }

            $contents = File::get($file->getPathname());
            $this->assertStringNotContainsString("from '@/i18n/en'", $contents, $file->getRelativePathname());
            $this->assertStringNotContainsString("from '@/i18n/pl'", $contents, $file->getRelativePathname());
        }
    }

    public function test_partial_german_pack_and_regional_document_language_fallback(): void
    {
        $catalog = new LanguageCatalog($this->directory);
        $dictionary = [
            '_nativeName' => 'Deutsch',
            'home.title' => 'Übersicht',
            'reports.document.page' => 'Seite',
        ];
        File::put($this->directory.'/de.json', json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->app->instance(LanguageCatalog::class, $catalog);

        $this->assertTrue($catalog->has('de'));
        $this->assertContains(['value' => 'de', 'label' => 'Deutsch'], $catalog->options());
        $this->get('/ui-translations/de')->assertOk()->assertExactJson($dictionary);

        $documents = new ReportDocumentLocale($catalog);
        $this->assertSame('Seite', $documents->text('de', 'reports.document.page', 'Page'));
        $this->assertSame('Seite', $documents->text('de-AT', 'reports.document.page', 'Page'));
        $this->assertSame('Draft', $documents->text('de', 'reports.document.draft', 'Draft'));
    }
}
