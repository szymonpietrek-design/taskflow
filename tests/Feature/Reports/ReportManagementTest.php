<?php

namespace Tests\Feature\Reports;

use App\Enums\ProjectVisibility;
use App\Enums\ReportFormat;
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
        $this->assertSame(ReportFormat::PROJECT_SUMMARY, $report->format);
        $this->assertNull($report->body);
        $this->assertNull($report->generated_at);

        $this->actingAs($owner)->get(route('reports.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')->where('reports.total', 1)
            ->where('reports.data.0.title', 'Weekly summary')->where('can_create', true));
    }

    public function test_work_report_requires_worklogs_and_gets_a_format_specific_title(): void
    {
        [$owner, $project] = $this->fixtures();

        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'format' => 'work_report', 'title' => '', 'sources' => ['tasks', 'messages'],
        ]))->assertSessionHasErrors('sources');
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'format' => 'not-a-format',
        ]))->assertSessionHasErrors('format');
        $this->assertDatabaseCount('reports', 0);

        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'format' => 'work_report', 'title' => '', 'sources' => ['worklogs', 'tasks'],
        ]))->assertSessionHasNoErrors();
        $report = Report::query()->sole();
        $this->assertSame(ReportFormat::WORK_REPORT, $report->format);
        $this->assertSame('Work report · Oct 1–7, 2026', $report->title);
        $this->actingAs($owner)->get(route('reports.index', $project))->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.format', 'work_report'));
    }

    public function test_report_uses_period_title_when_title_is_omitted(): void
    {
        [$owner, $project] = $this->fixtures();
        $payload = $this->payload();
        unset($payload['title']);

        $this->actingAs($owner)->post(route('reports.store', $project), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Project report · Oct 1–7, 2026', Report::query()->sole()->title);
    }

    public function test_blank_title_generates_name_and_custom_title_is_preserved(): void
    {
        [$owner, $project] = $this->fixtures();

        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'title' => '   ', 'period_start' => '2026-10-11', 'period_end' => '2026-10-11',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'title' => '  Release retrospective  ',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['Project report · Oct 11, 2026', 'Release retrospective'],
            Report::query()->orderBy('id')->pluck('title')->all(),
        );
    }

    public function test_generated_title_formats_period_across_months_and_years(): void
    {
        [$owner, $project] = $this->fixtures();

        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'title' => null, 'period_start' => '2026-09-28', 'period_end' => '2026-10-04',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'title' => '', 'period_start' => '2026-12-28', 'period_end' => '2027-01-03',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['Project report · Sep 28 – Oct 4, 2026', 'Project report · Dec 28, 2026 – Jan 3, 2027'],
            Report::query()->orderBy('id')->pluck('title')->all(),
        );
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
        ]))->assertSessionHasErrors(['period_start']);
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'title' => str_repeat('x', 161),
        ]))->assertSessionHasErrors(['title']);
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

    public function test_worklogs_source_uses_work_date_inclusively_and_only_current_project(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ['worklogs']);
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id, 'created_by' => $owner->id,
        ]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $otherStatus = TaskStatus::factory()->create(['project_id' => $other->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'status_id' => $status->id,
            'created_at' => '2026-09-15 10:00:00',
        ]);
        $otherTask = Task::factory()->create([
            'project_id' => $other->id, 'created_by' => $owner->id, 'status_id' => $otherStatus->id,
        ]);

        foreach ([
            ['2026-09-30', 60],
            ['2026-10-01', 60],
            ['2026-10-07', 105],
            ['2026-10-08', 50],
        ] as [$date, $minutes]) {
            $task->worklogs()->create([
                'user_id' => $owner->id, 'work_date' => $date,
                'duration_minutes' => $minutes, 'description' => null, 'is_creative' => false,
            ]);
        }
        $otherTask->worklogs()->create([
            'user_id' => $owner->id, 'work_date' => '2026-10-01',
            'duration_minutes' => 400, 'description' => null, 'is_creative' => false,
        ]);

        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Show')
            ->where('source_counts.worklogs.entries', 2)
            ->where('source_counts.worklogs.total_minutes', 165)
            ->where('source_counts.tasks', null)
            ->where('source_counts.messages', null)
            ->where('source_counts.activity', null));
    }

    public function test_worklog_breakdown_splits_time_by_contributor_task_and_creative_work(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ['worklogs']);
        $colleague = User::factory()->create(['name' => 'Alex Developer']);
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id, 'created_by' => $owner->id,
        ]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $foreignStatus = TaskStatus::factory()->create(['project_id' => $other->id]);
        $first = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'status_id' => $status->id,
            'number' => 17, 'title' => 'Improve the task page',
        ]);
        $second = Task::factory()->create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'status_id' => $status->id,
            'number' => 18, 'title' => 'Release documentation',
        ]);
        $foreign = Task::factory()->create([
            'project_id' => $other->id, 'created_by' => $owner->id, 'status_id' => $foreignStatus->id,
        ]);

        foreach ([
            [$first, $owner->id, '2026-10-01', 60, true],
            [$first, $owner->id, '2026-10-01', 45, false],
            [$first, $colleague->id, '2026-10-07', 75, true],
            [$second, $owner->id, '2026-10-03', 135, false],
            [$second, null, '2026-10-04', 30, true], // A deleted contributor retains their historical time.
            [$first, $owner->id, '2026-09-30', 40, true], // Outside the saved date range.
            [$first, $owner->id, '2026-10-08', 40, true], // Outside the saved date range.
            [$foreign, $owner->id, '2026-10-02', 240, true], // Belongs to a different project.
        ] as [$task, $userId, $date, $minutes, $creative]) {
            $task->worklogs()->create([
                'user_id' => $userId,
                'work_date' => $date,
                'duration_minutes' => $minutes,
                'description' => null,
                'is_creative' => $creative,
            ]);
        }

        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Show')
            ->where('source_counts.worklogs.entries', 5)
            ->where('source_counts.worklogs.total_minutes', 345)
            ->where('source_counts.worklogs.creative_entries', 3)
            ->where('source_counts.worklogs.creative_minutes', 165)
            ->has('source_counts.worklogs.by_user', 3)
            ->where('source_counts.worklogs.by_user.0.user_id', $owner->id)
            ->where('source_counts.worklogs.by_user.0.total_minutes', 240)
            ->where('source_counts.worklogs.by_user.0.creative_minutes', 60)
            ->where('source_counts.worklogs.by_user.0.entries', 3)
            ->where('source_counts.worklogs.by_user.1.user_id', $colleague->id)
            ->where('source_counts.worklogs.by_user.1.name', 'Alex Developer')
            ->where('source_counts.worklogs.by_user.1.total_minutes', 75)
            ->where('source_counts.worklogs.by_user.2.user_id', null)
            ->where('source_counts.worklogs.by_user.2.name', 'Former user')
            ->where('source_counts.worklogs.by_user.2.total_minutes', 30)
            ->has('source_counts.worklogs.by_task', 2)
            ->where('source_counts.worklogs.by_task.0.task_id', $first->id)
            ->where('source_counts.worklogs.by_task.0.number', 17)
            ->where('source_counts.worklogs.by_task.0.title', 'Improve the task page')
            ->where('source_counts.worklogs.by_task.0.total_minutes', 180)
            ->where('source_counts.worklogs.by_task.0.creative_minutes', 135)
            ->where('source_counts.worklogs.by_task.0.entries', 3)
            ->where('source_counts.worklogs.by_task.1.task_id', $second->id)
            ->where('source_counts.worklogs.by_task.1.total_minutes', 165)
            ->where('source_counts.worklogs.by_task.1.creative_minutes', 30));
    }

    public function test_report_accepts_four_sources_and_empty_worklogs_show_zero(): void
    {
        [$owner, $project] = $this->fixtures();
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'sources' => ['tasks', 'messages', 'activity', 'worklogs'],
        ]))->assertSessionHasNoErrors();
        $report = Report::query()->sole();
        $this->assertSame(['tasks', 'messages', 'activity', 'worklogs'], $report->sources);

        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('source_counts.worklogs.entries', 0)
            ->where('source_counts.worklogs.total_minutes', 0)
            ->where('source_counts.worklogs.creative_entries', 0)
            ->where('source_counts.worklogs.creative_minutes', 0)
            ->has('source_counts.worklogs.by_user', 0)
            ->has('source_counts.worklogs.by_task', 0));
    }

    public function test_unselected_sources_are_not_queried_for_preview(): void
    {
        [$owner, $project] = $this->fixtures();
        $report = $this->report($project, $owner, ['tasks']);
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('source_counts.tasks', 0)
            ->where('source_counts.messages', null)
            ->where('source_counts.activity', null)
            ->where('source_counts.worklogs', null));
    }

    public function test_reader_can_create_only_personal_reports_and_cannot_read_team_reports(): void
    {
        [, $project] = $this->fixtures();
        $owner = User::findOrFail($project->created_by);
        $teamReport = $this->report($project, $owner);
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($reader)->get(route('reports.index', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_create', true)->where('can_select_people', false)
                ->where('reports.total', 0));
        $this->actingAs($reader)->get(route('reports.show', [$project, $teamReport]))->assertForbidden();
        $this->actingAs($reader)->post(route('reports.store', $project), $this->payload([
            'contributor_ids' => [$owner->id],
        ]))->assertSessionHasErrors('contributor_ids');
        $this->actingAs($reader)->post(route('reports.store', $project), $this->payload())
            ->assertSessionHasNoErrors();
        $personal = Report::query()->where('created_by', $reader->id)->sole();
        $this->assertSame([$reader->id], $personal->contributor_ids);
        $this->actingAs($reader)->get(route('reports.show', [$project, $personal]))
            ->assertInertia(fn (Assert $page) => $page->where('can_generate', true));
        $this->actingAs($reader)->delete(route('reports.destroy', [$project, $teamReport]))->assertForbidden();
        $this->actingAs($owner)->get(route('reports.index', $project))
            ->assertInertia(fn (Assert $page) => $page->where('reports.total', 2));
    }

    public function test_manager_can_filter_reports_to_selected_contributors(): void
    {
        [$owner, $project] = $this->fixtures();
        $colleague = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $colleague->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $external = User::factory()->create();
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'sources' => ['worklogs'], 'contributor_ids' => [$external->id],
        ]))->assertSessionHasErrors('contributor_ids');
        $this->actingAs($owner)->post(route('reports.store', $project), $this->payload([
            'sources' => ['worklogs'], 'contributor_ids' => [$colleague->id],
        ]))->assertSessionHasNoErrors();
        $report = Report::query()->sole();
        $this->assertSame([$colleague->id], $report->contributor_ids);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status_id' => $status->id, 'created_by' => $owner->id]);
        foreach ([[$owner->id, 120], [$colleague->id, 45]] as [$id, $minutes]) {
            $task->worklogs()->create(['user_id' => $id, 'work_date' => '2026-10-03',
                'duration_minutes' => $minutes, 'description' => null, 'is_creative' => false]);
        }
        $this->actingAs($owner)->get(route('reports.show', [$project, $report]))->assertInertia(fn (Assert $page) => $page
            ->where('source_counts.worklogs.total_minutes', 45)
            ->where('source_counts.worklogs.entries', 1)
            ->where('report.contributor_ids', [$colleague->id]));
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
        $this->actingAs($owner)->get(route('reports.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reports.data.0.id', $report->id));
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
