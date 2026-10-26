<?php

namespace Tests\Feature\Reports;

use App\Enums\ReportFormat;
use App\Models\Project;
use App\Models\Report;
use App\Support\I18n\LanguageCatalog;
use App\Support\Reports\ReportDocument;
use App\Support\Reports\ReportDocumentLocale;
use App\Support\Reports\ReportPdfPresentation;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportDocumentLocaleTest extends TestCase
{
    private string $languageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->languageDir = sys_get_temp_dir().'/taskflow-document-locales-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->languageDir);
        foreach (['en', 'pl'] as $locale) {
            File::copy(lang_path("{$locale}.json"), "{$this->languageDir}/{$locale}.json");
        }
        $this->app->instance(LanguageCatalog::class, new LanguageCatalog($this->languageDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->languageDir);
        parent::tearDown();
    }

    public function test_report_copy_follows_saved_language_even_when_viewer_uses_english(): void
    {
        app()->setLocale('en');
        $report = $this->report('pl', 'Work report · Oct 1–7, 2026', ReportFormat::WORK_REPORT);
        $html = app(ReportDocument::class)->view($report, true)->render();

        $this->assertStringContainsString('Raport pracy · 1–7 paź 2026', $html);
        $this->assertStringContainsString('Wersja robocza', $html);
        $this->assertSame('Strona', app(ReportDocumentLocale::class)->text('pl', 'reports.document.page', 'Page'));
        $this->assertStringNotContainsString('>Draft<', $html);
    }

    public function test_file_installed_german_translates_report_title_draft_and_pdf_page(): void
    {
        File::put($this->languageDir.'/de.json', json_encode([
            '_nativeName' => 'Deutsch',
            'Technical report' => 'Technischer Projektbericht',
            'reports.document.draft' => 'Entwurf',
            'reports.document.page' => 'Seite',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $report = $this->report('de', 'Technical report · Oct 1–7, 2026', ReportFormat::TECHNICAL_REPORT);
        $html = app(ReportDocument::class)->view($report, true)->render();

        $this->assertStringContainsString('Technischer Projektbericht ·', $html);
        $this->assertStringContainsString('Entwurf', $html);
        $this->assertSame('Seite', app(ReportDocumentLocale::class)->text('de-DE', 'reports.document.page', 'Page'));
        $this->assertStringNotContainsString('Wersja robocza', $html);

        // The public document keeps the saved title/body but still uses the report's locale for labels.
        $publicHtml = app(ReportDocument::class)->view($report, true, true)->render();
        $this->assertStringContainsString('Technical report · Oct 1–7, 2026', $publicHtml);
        $this->assertStringContainsString('Entwurf', $publicHtml);
        $this->assertStringContainsString('Report details.', $publicHtml);
    }

    public function test_partial_pack_and_missing_pack_use_safe_fallback(): void
    {
        File::put($this->languageDir.'/fr.json', json_encode([
            '_nativeName' => 'Français',
            'reports.document.draft' => 'Brouillon',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $copy = app(ReportDocumentLocale::class);
        $this->assertSame('Brouillon', $copy->text('fr', 'reports.document.draft', 'Draft'));
        $this->assertSame('Page', $copy->text('fr', 'reports.document.page', 'Page'));
        $this->assertSame('Page', $copy->text('no', 'reports.document.page', 'Page'));
    }

    public function test_custom_titles_are_not_rewritten(): void
    {
        $report = $this->report('pl', 'Raport dla klienta', ReportFormat::TECHNICAL_REPORT);
        $this->assertSame('Raport dla klienta', app(ReportPdfPresentation::class)->title($report));
    }

    private function report(string $locale, string $title, ReportFormat $format): Report
    {
        $report = new Report([
            'title' => $title,
            'format' => $format,
            'generated_locale' => $locale,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'body' => "## Summary\n\nReport details.",
        ]);
        $report->setRelation('project', new Project(['name' => 'Demo']));

        return $report;
    }
}
