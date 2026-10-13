<?php

namespace Tests\Feature\Reports;

use App\Enums\ReportFormat;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskWorklog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Reports\ReportRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_summary_can_be_edited_and_safe_preview_uses_saved_markdown(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ReportFormat::PROJECT_SUMMARY, '# Original');
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page->where('editable_content', '# Original')->where('can_edit', true));

        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => "# Revised\n\nNew **summary** <script>alert(1)</script>",
            'version' => $this->version($report),
        ])->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertStringContainsString('New **summary**', $report->body);
        $this->assertNotNull($report->edited_at);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.published_at', null)
                ->where('report_html', fn ($html) => str_contains($html, '<strong>summary</strong>') && ! str_contains($html, '<script')));
    }

    public function test_detailed_report_all_sections_including_rows_and_totals_can_be_edited(): void
    {
        [$owner, $project] = $this->fixtures();
        $body = "# Raport pracy\n\n**Projekt:** Demo\n\n## Podsumowanie\n\nStare podsumowanie\n\n## Wykonane prace\n\n| Data | Zadanie | Rezultat | Czas |\n| --- | --- | --- | --- |\n| 2026-10-01 | NSTAR-7 | Brak danych | 2h |\n\n## Zestawienie czasu\n\n- **Łączny czas:** 2h";
        $report = $this->report($project, $owner, ReportFormat::WORK_REPORT, $body);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page->where('editable_content', $body));

        // Editing the document does not modify any source TaskWorklogs.
        $task = Task::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);
        $log = TaskWorklog::query()->create([
            'task_id' => $task->id, 'user_id' => $owner->id, 'work_date' => '2026-10-01',
            'duration_minutes' => 120, 'description' => null, 'is_creative' => false,
        ]);
        $updated = str_replace(
            ['Stare podsumowanie', 'Brak danych', '2h'],
            ['Nowy opis realizacji.', 'Zmieniony rezultat', '3h'],
            $body,
        );
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => $updated, 'version' => $this->version($report),
        ])->assertSessionHasNoErrors();

        $saved = $report->fresh();
        $this->assertSame($updated, $saved->body);
        $this->assertNotNull($saved->edited_at);
        $this->assertSame(120, $log->fresh()->duration_minutes);
        $this->assertNull($log->fresh()->description);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('editable_content', $updated)
                ->where('report_html', fn ($html) => str_contains($html, 'Zmieniony rezultat') && str_contains($html, '3h')));
    }

    public function test_publish_requires_content_and_blocks_edits_until_moved_to_draft(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ReportFormat::PROJECT_SUMMARY, null);
        $this->actingAs($owner)->post(route('reports.publish', [$project, $report]))->assertSessionHasErrors('publication');
        $report->update(['body' => '# Completed', 'generated_at' => now()]);
        $this->actingAs($owner)->post(route('reports.publish', [$project, $report]))->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->published_at);
        $this->assertSame($owner->id, $report->fresh()->published_by);
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => '# Not allowed', 'version' => $this->version($report),
        ])->assertForbidden();
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'pl'])
            ->assertSessionHasErrors('ai_generation');
        $this->assertSame('# Completed', $report->fresh()->body);
        $this->actingAs($owner)->post(route('reports.unpublish', [$project, $report]))->assertSessionHasNoErrors();
        $this->assertNull($report->fresh()->published_at);
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => '# Updated', 'version' => $this->version($report),
        ])->assertSessionHasNoErrors();
    }

    public function test_stale_save_and_invalid_or_empty_content_are_rejected(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ReportFormat::PROJECT_SUMMARY, 'Before');
        $old = $this->version($report);
        $report->update(['body' => 'Changed elsewhere']);
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => 'Overwrite', 'version' => $old,
        ])->assertSessionHasErrors('content');
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => '', 'version' => $this->version($report),
        ])->assertSessionHasErrors('content');
        $this->assertSame('Changed elsewhere', $report->fresh()->body);
    }

    public function test_team_report_is_not_editable_by_regular_member_and_archived_report_is_locked(): void
    {
        [$owner, $project] = $this->fixtures();
        $member = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $project->workspace_id,
            'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
        $report = $this->report($project, $owner, ReportFormat::PROJECT_SUMMARY, '# Team work');
        $payload = ['content' => 'Hacked', 'version' => $this->version($report)];
        $this->actingAs($member)->put(route('reports.content.update', [$project, $report]), $payload)->assertForbidden();
        $this->actingAs($member)->post(route('reports.publish', [$project, $report]))->assertForbidden();
        $this->actingAs($member)->post(route('reports.unpublish', [$project, $report]))->assertForbidden();
        $this->actingAs($owner)->put(route('reports.content.update', [Project::factory()->create(), $report]), $payload)->assertNotFound();
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), $payload)->assertForbidden();
        $this->actingAs($owner)->post(route('reports.publish', [$project, $report]))->assertForbidden();
    }

    public function test_legacy_unstructured_detailed_document_can_be_fully_edited(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ReportFormat::WORK_REPORT, 'Legacy text without a worklog table');
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page->where('editable_content', 'Legacy text without a worklog table'));
        $this->actingAs($owner)->put(route('reports.content.update', [$project, $report]), [
            'content' => "# Report\n\nNew section", 'version' => $this->version($report),
        ])->assertSessionHasNoErrors();
        $this->assertSame("# Report\n\nNew section", $report->fresh()->body);
    }

    /** @return array{User,Project} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        return [$owner, $project];
    }

    private function report(Project $project, User $owner, ReportFormat $format, ?string $body): Report
    {
        return Report::query()->create(['project_id' => $project->id, 'created_by' => $owner->id,
            'title' => 'Review', 'format' => $format, 'period_start' => '2026-10-01', 'period_end' => '2026-10-07',
            'sources' => $format === ReportFormat::WORK_REPORT ? ['worklogs'] : ['tasks'],
            'body' => $body, 'generated_at' => $body !== null ? now() : null]);
    }

    private function version(Report $report): string
    {
        return app(ReportRevision::class)->token($report->fresh());
    }
}
