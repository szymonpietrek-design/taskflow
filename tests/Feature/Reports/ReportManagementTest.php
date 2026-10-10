<?php

namespace Tests\Feature\Reports;

use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_creates_report_with_selected_sources_and_dates(): void
    {
        [$owner, $project] = $this->fixtures();

        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload())
            ->assertRedirect();

        $report = Report::query()->sole();
        $this->assertSame($project->id, $report->project_id);
        $this->assertSame($owner->id, $report->created_by);
        $this->assertSame('Weekly summary', $report->title);
        $this->assertSame('2026-10-01', $report->period_start->toDateString());
        $this->assertSame('2026-10-07', $report->period_end->toDateString());
        $this->assertSame(['tasks', 'messages', 'activity'], $report->sources);
        $this->assertNull($report->body);
        $this->assertNull($report->generated_at);

        $this->actingAs($owner)->get(route('reports.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('reports/Index')->where('reports.total', 1)
                ->where('reports.data.0.title', 'Weekly summary')->where('can_create', true));
    }

    public function test_validation_rejects_invalid_period_and_sources(): void
    {
        [$owner, $project] = $this->fixtures();

        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'period_end' => '2026-09-30', 'sources' => [],
        ]))->assertSessionHasErrors(['period_end', 'sources']);
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'sources' => ['tasks', 'tasks', 'secret'],
        ]))->assertSessionHasErrors(['sources.1', 'sources.2']);
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'period_start' => '10/01/2026', 'title' => '   ',
        ]))->assertSessionHasErrors(['period_start', 'title']);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_source_overview_uses_inclusive_period_and_never_counts_other_projects(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner);
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id, 'created_by' => $owner->id,
        ]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $foreignStatus = TaskStatus::factory()->create(['project_id' => $other->id]);
        $within = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'status_id' => $status->id,
            'created_at' => '2026-09-15 10:00:00', 'completed_at' => '2026-10-07 22:30:00',
        ]);
        Task::factory()->create([
            'project_id' => $other->id, 'created_by' => $owner->id, 'status_id' => $foreignStatus->id,
            'created_at' => '2026-10-03 11:00:00',
        ]);
        TaskMessage::factory()->create([
            'task_id' => $within->id, 'user_id' => $owner->id, 'created_at' => '2026-10-01 00:00:00',
        ]);
        TaskMessage::factory()->create([
            'task_id' => $within->id, 'user_id' => $owner->id, 'created_at' => '2026-10-08 00:00:00',
        ]);
        TaskActivity::factory()->create([
            'task_id' => $within->id, 'user_id' => $owner->id, 'created_at' => '2026-10-07 23:59:00',
        ]);

        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('reports/Show')
                ->where('source_counts.tasks', 1)
                ->where('source_counts.messages', 1)
                ->where('source_counts.activity', 1));
    }

    public function test_unselected_sources_are_not_queried_for_preview(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ['tasks']);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('source_counts.tasks', 0)
                ->where('source_counts.messages', null)
                ->where('source_counts.activity', null));
    }

    public function test_workspace_reader_can_view_but_not_create_or_delete_reports(): void
    {
        [, $project] = $this->fixtures();
        $owner = User::findOrFail($project->created_by);
        $report = $this->report($project, $owner);
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($reader)->get(route('reports.index', $project))
            ->assertInertia(fn (Assert $page) => $page->where('can_create', false));
        $this->actingAs($reader)->get(route('reports.show', [$project, $report]))
            ->assertInertia(fn (Assert $page) => $page->where('can_delete', false));
        $this->actingAs($reader)->post(route('reports.store', $project), $this->payload())
            ->assertForbidden();
        $this->actingAs($reader)->delete(route('reports.destroy', [$project, $report]))
            ->assertForbidden();
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_foreign_project_report_is_not_accessible(): void
    {
        [$owner, $project] = $this->fixtures();
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id, 'created_by' => $owner->id,
        ]);
        $report = $this->report($other, $owner);

        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))->assertNotFound();
        $this->actingAs($owner)->delete(route('reports.destroy', [$project, $report]))->assertNotFound();
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_outsider_cannot_view_even_workspace_visible_project_reports(): void
    {
        [, $project] = $this->fixtures();
        $report = $this->report($project, User::findOrFail($project->created_by));
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('reports.index', $project))->assertForbidden();
        $this->actingAs($outsider)->get(route('reports.show', [$project, $report]))->assertForbidden();
        $this->actingAs($outsider)->post(route('reports.store', $project), $this->payload())->assertForbidden();
    }

    public function test_archived_project_can_show_reports_but_not_modify_them(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner);
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))->assertOk();
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload())->assertForbidden();
        $this->actingAs($owner)->delete(route('reports.destroy', [$project, $report]))->assertForbidden();
    }

    public function test_manager_can_delete_report_without_affecting_project(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner);
        $this->actingAs($owner)->delete(route('reports.destroy', [$project, $report]))
            ->assertRedirect(route('reports.index', $project));
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('projects', 1);
    }

    /** @return array{User,Project} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id, 'created_by' => $owner->id,
            'visibility' => ProjectVisibility::WORKSPACE,
        ]);

        return [$owner, $project];
    }

    /** @return array<string, mixed> */
    private function payload(array $changes = []): array
    {
        return array_replace([
            'title' => 'Weekly summary',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'sources' => ['tasks', 'messages', 'activity'],
        ], $changes);
    }

    /** @param array<int, string> $sources */
    private function report(Project $project, User $user, array $sources = ['tasks', 'messages', 'activity']): Report
    {
        return Report::query()->create([
            'project_id' => $project->id, 'created_by' => $user->id,
            'title' => 'Weekly summary', 'period_start' => '2026-10-01',
            'period_end' => '2026-10-07', 'sources' => $sources,
        ]);
    }
}
