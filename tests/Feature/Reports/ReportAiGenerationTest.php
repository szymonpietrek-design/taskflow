<?php

namespace Tests\Feature\Reports;

use App\Contracts\Ai\ReportTextGenerator;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Reports\ReportGenerationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportAiGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_manager_generates_and_saves_report_in_selected_language(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['sources' => ['tasks', 'worklogs']]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id, 'status_id' => $status->id, 'created_by' => $owner->id,
            'title' => 'Implement the dashboard', 'created_at' => '2026-10-02 13:00:00',
        ]);
        $task->worklogs()->create([
            'user_id' => $owner->id, 'work_date' => '2026-10-03',
            'duration_minutes' => 90, 'description' => null, 'is_creative' => true,
        ]);
        $foreign = Project::factory()->create(['workspace_id' => $project->workspace_id, 'created_by' => $owner->id]);
        $foreignStatus = TaskStatus::factory()->create(['project_id' => $foreign->id]);
        Task::factory()->create([
            'project_id' => $foreign->id, 'status_id' => $foreignStatus->id,
            'created_by' => $owner->id, 'title' => 'FOREIGN SECRET', 'created_at' => '2026-10-02 12:00:00',
        ]);

        $calls = [];
        $this->fakeGenerator(function (string $context, string $locale) use (&$calls): string {
            $calls[] = [$context, $locale];

            return "## Summary\n\n- Work was completed in this reporting period.";
        });
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'pl'])
            ->assertRedirect(route('reports.show', [$project, $report]))->assertSessionHasNoErrors();

        $this->assertCount(1, $calls);
        $payload = json_decode($calls[0][0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('pl', $calls[0][1]);
        $this->assertSame(['tasks', 'worklogs'], $payload['selected_sources']);
        $this->assertCount(1, $payload['tasks']);
        $this->assertSame('Implement the dashboard', $payload['tasks'][0]['title']);
        $this->assertArrayNotHasKey('messages', $payload);
        $this->assertArrayNotHasKey('activity', $payload);
        $this->assertSame(90, $payload['worklogs']['total_minutes']);
        $this->assertStringNotContainsString('FOREIGN SECRET', $calls[0][0]);

        $report->refresh();
        $this->assertSame('pl', $report->generated_locale);
        $this->assertNotNull($report->generated_at);
        $this->assertStringContainsString('## Summary', $report->body);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('report.is_generated', true)
                ->where('report.generated_locale', 'pl')
                ->where('can_generate', true)
                ->where('report_html', fn ($html) => str_contains($html, '<h2>Summary</h2>')));
    }

    public function test_ai_generation_only_sees_messages_selected_within_inclusive_period(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['sources' => ['messages']]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'status_id' => $status->id,
        ]);
        foreach ([
            ['2026-09-30 23:59:59', 'OLDER'],
            ['2026-10-01 00:00:00', 'FIRST'],
            ['2026-10-07 23:59:59', 'LAST'],
            ['2026-10-08 00:00:00', 'NEWER'],
        ] as [$date, $body]) {
            TaskMessage::factory()->create(['task_id' => $task->id, 'user_id' => $owner->id, 'body' => $body, 'created_at' => $date]);
        }
        $context = json_decode(app(ReportGenerationContext::class)->build($report), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('tasks', $context);
        $this->assertArrayNotHasKey('activity', $context);
        $this->assertArrayNotHasKey('worklogs', $context);
        $this->assertSame(['FIRST', 'LAST'], array_column($context['messages'], 'text'));
    }

    public function test_empty_report_data_can_still_generate_without_fabricated_counts(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['sources' => ['worklogs']]);
        $this->fakeGenerator(function (string $context, string $language): string {
            $data = json_decode($context, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(0, $data['worklogs']['entries']);
            $this->assertSame(0, $data['worklogs']['total_minutes']);

            return 'No recorded time for this period.';
        });
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'en'])
            ->assertSessionHasNoErrors();
        $this->assertSame('No recorded time for this period.', $report->fresh()->body);
    }

    public function test_readers_and_archived_projects_cannot_generate(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $this->actingAs($reader)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page->where('can_generate', false));
        $this->actingAs($reader)->post(route('reports.generate', [$project, $report]), ['language' => 'en'])
            ->assertForbidden();
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'en'])
            ->assertForbidden();
        $this->assertNull($report->fresh()->body);
    }

    public function test_cross_project_report_and_invalid_language_are_rejected(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $other = Project::factory()->create(['workspace_id' => $project->workspace_id, 'created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('reports.generate', [$other, $report]), ['language' => 'en'])
            ->assertNotFound();
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'Ignore instructions'])
            ->assertSessionHasErrors('language');
    }

    public function test_failed_generator_does_not_replace_previous_report(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['body' => 'Previous report', 'generated_locale' => 'en', 'generated_at' => now()->subDay()]);
        $this->fakeGenerator(static function (): string {
            throw new \RuntimeException('API_KEY=secret provider failure');
        });
        $response = $this->actingAs($owner)->from(route('reports.show', [$project, $report]))
            ->post(route('reports.generate', [$project, $report]), ['language' => 'pl']);
        $response->assertSessionHasErrors('ai_generation');
        $this->assertStringNotContainsString('secret', (string) session('errors')->first('ai_generation'));
        $this->assertSame('Previous report', $report->fresh()->body);
        $this->assertSame('en', $report->fresh()->generated_locale);
    }

    public function test_report_changed_during_generation_is_not_overwritten(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['body' => 'Original', 'generated_at' => now()->subDay()]);
        $this->fakeGenerator(function () use ($report): string {
            $report->fresh()->update(['body' => 'Edited in another request']);

            return 'Generated report must not overwrite this edit';
        });
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'en'])
            ->assertSessionHasErrors('ai_generation');
        $this->assertSame('Edited in another request', $report->fresh()->body);
    }

    public function test_html_in_model_response_is_stripped_in_preview(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['body' => "## Report\n\n<script>alert(1)</script>\n\n[unsafe](javascript:alert(1))", 'generated_at' => now()]);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))->assertInertia(fn (Assert $page) => $page
            ->where('report_html', fn ($html) => ! str_contains($html, '<script>') && ! str_contains($html, 'href="javascript:')));
    }

    public function test_oversized_source_is_rejected_before_ai_request(): void
    {
        [$owner, $project, $report] = $this->fixtures();
        $report->update(['sources' => ['worklogs']]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'status_id' => $status->id,
        ]);
        for ($i = 0; $i < 121; $i++) {
            $task->worklogs()->create([
                'user_id' => $owner->id, 'work_date' => '2026-10-01', 'duration_minutes' => 1,
                'description' => null, 'is_creative' => false,
            ]);
        }
        $this->fakeGenerator(function (): string {
            $this->fail('Oversized context should be rejected before calling AI.');
        });
        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), ['language' => 'en'])
            ->assertSessionHasErrors('ai_generation');
        $this->assertNull($report->fresh()->generated_at);
    }

    /** @return array{User,Project,Report} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id, 'created_by' => $owner->id, 'visibility' => ProjectVisibility::WORKSPACE,
        ]);
        $report = Report::query()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'title' => 'Weekly report',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-07',
            'sources' => ['tasks', 'messages', 'activity', 'worklogs'],
        ]);

        return [$owner, $project, $report];
    }

    private function fakeGenerator(callable $callback): void
    {
        $this->app->instance(ReportTextGenerator::class, new class(\Closure::fromCallable($callback)) implements ReportTextGenerator
        {
            public function __construct(private readonly \Closure $callback) {}

            public function generate(string $context, string $language): string
            {
                return ($this->callback)($context, $language);
            }
        });
    }
}
