<?php

namespace Tests\Feature\Reports;

use App\Enums\ReportFormat;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Reports\ReportDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_published_report_can_be_shared_and_guest_sees_only_a_clean_document(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $this->actingAs($owner)->post(route('reports.share.store', [$project, $report]))->assertSessionHasErrors('sharing');
        $report->update(['published_at' => now(), 'published_by' => $owner->id]);
        $this->actingAs($owner)->post(route('reports.share.store', [$project, $report]))->assertSessionHasNoErrors();
        $report->refresh();
        $token = $report->share_token;
        $this->assertNotNull($token);
        $this->assertSame(48, strlen($token));
        $this->assertStringNotContainsString($token, \DB::table('reports')->where('id', $report->id)->value('share_token'));
        $url = route('reports.public', ['report' => $report, 'token' => $token]);
        auth()->logout();
        $publicResponse = $this->get($url);
        $publicResponse->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('class="public-report-html"', false)
            ->assertSee('Visible summary')
            ->assertDontSee('<script', false)
            ->assertDontSee('My Worklog');

        // Symfony can normalize Cache-Control directive order without changing its meaning.
        $cacheDirectives = array_map('trim', explode(',', $publicResponse->headers->get('Cache-Control', '')));
        sort($cacheDirectives);
        $this->assertSame(['max-age=0', 'no-store', 'private'], $cacheDirectives);
        $this->get(route('reports.public', ['report' => $report, 'token' => str_repeat('a', 48)]))->assertNotFound();
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page->where('share_url', $url)->where('can_share', true));
        $this->actingAs($owner)->delete(route('reports.share.destroy', [$project, $report]))->assertSessionHasNoErrors();
        $this->assertNull($report->fresh()->share_token);
        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_unpublishing_revokes_link_and_a_new_publish_needs_a_fresh_link(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['published_at' => now(), 'published_by' => $owner->id]);
        $this->actingAs($owner)->post(route('reports.share.store', [$project, $report]));
        $token = $report->fresh()->share_token;
        $url = route('reports.public', ['report' => $report, 'token' => $token]);
        $this->actingAs($owner)->post(route('reports.unpublish', [$project, $report]))->assertSessionHasNoErrors();
        $this->assertNull($report->fresh()->share_token);
        $this->actingAs($owner)->post(route('reports.publish', [$project, $report]))->assertSessionHasNoErrors();
        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_only_owners_can_share_and_reports_from_other_projects_cannot_be_accessed(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['published_at' => now(), 'published_by' => $owner->id]);
        $member = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $project->workspace_id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
        $this->actingAs($member)->post(route('reports.share.store', [$project, $report]))->assertForbidden();
        $this->actingAs($member)->delete(route('reports.share.destroy', [$project, $report]))->assertForbidden();
        $other = Project::factory()->create();
        $this->actingAs($owner)->post(route('reports.share.store', [$other, $report]))->assertNotFound();
        $this->assertNull($report->fresh()->share_token);
    }

    public function test_pdf_download_checks_permissions_and_uses_saved_content(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        // Verify the saved content is safely rendered into the PDF document template.
        $html = app(ReportDocument::class)->view($report, true)->render();
        $this->assertStringContainsString('Visible summary', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('class="public-report-html"', $html);

        // Render a real PDF: ReportPdfRenderer is final and cannot be mocked by Mockery.
        $response = $this->actingAs($owner)->get(route('reports.pdf', [$project, $report]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload();
        $this->assertStringStartsWith('%PDF-', $response->baseResponse->getContent());
        auth()->logout();
        $this->get(route('reports.pdf', [$project, $report]))->assertRedirect(route('login'));
    }

    public function test_pdf_uses_polish_date_and_title_without_repeating_generated_work_report_header(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update([
            'format' => ReportFormat::WORK_REPORT,
            'title' => 'Work report · Sep 11 – Oct 11, 2026',
            'generated_locale' => 'pl',
            'period_start' => '2026-09-11',
            'period_end' => '2026-10-11',
            'body' => "# Raport pracy\n\n**Projekt:** {$project->name}\n**Okres:** 2026-09-11 – 2026-10-11\n\n## Podsumowanie\n\nZapisana treść.\n",
        ]);

        $pdfHtml = app(ReportDocument::class)->view($report->fresh(), false)->render();
        $this->assertStringContainsString('Raport pracy · 11 wrz – 11 paź 2026', $pdfHtml);
        $this->assertStringContainsString('class="pdf-report"', $pdfHtml);
        $this->assertStringContainsString('font-size: 9.2pt;', $pdfHtml);
        $this->assertSame(1, substr_count($pdfHtml, 'Raport pracy'));
        $this->assertStringNotContainsString('**Okres:**', $pdfHtml);
        $this->assertStringNotContainsString('2026-09-11 – 2026-10-11', $pdfHtml);
        $this->assertStringContainsString('Zapisana treść.', $pdfHtml);

        $publicHtml = app(ReportDocument::class)->view($report->fresh(), false, true)->render();
        $this->assertStringContainsString('Work report · Sep 11 – Oct 11, 2026', $publicHtml);
        $this->assertStringContainsString('class="public-report-html"', $publicHtml);
        $this->assertStringContainsString('<h1>Raport pracy</h1>', $publicHtml);
    }

    public function test_pdf_keeps_custom_title_and_manually_edited_heading(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update([
            'format' => ReportFormat::WORK_REPORT,
            'title' => 'Raport dla klienta',
            'generated_locale' => 'pl',
            'body' => "# Własny nagłówek\n\n**Projekt:** {$project->name}\n**Okres:** 2026-10-01 – 2026-10-07\n\nTreść.",
        ]);

        $pdfHtml = app(ReportDocument::class)->view($report->fresh(), false)->render();
        $this->assertStringContainsString('Raport dla klienta', $pdfHtml);
        $this->assertStringContainsString('<h1>Własny nagłówek</h1>', $pdfHtml);
        $this->assertStringContainsString('2026-10-01 – 2026-10-07', $pdfHtml);
    }

    /** @return array{User,Project,Report} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $report = Report::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Progress report',
            'format' => ReportFormat::PROJECT_SUMMARY,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'sources' => ['tasks'],
            'body' => "# Visible summary\n\n<script>alert(1)</script>",
            'generated_at' => now(),
        ]);

        return [$owner, $project, $report];
    }
}
