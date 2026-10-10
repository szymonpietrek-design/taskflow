<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskWorklogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_multiple_entries_same_day_and_on_other_days_are_summed(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        foreach ([
            ['2026-05-12', 1, 0, 'Initial changes'],
            ['2026-05-12', 2, 0, 'API integration'],
            ['2026-05-13', 3, 0, 'Tests'],
            ['2026-05-14', 2, 0, 'Refactor'],
        ] as [$date, $hours, $minutes, $description]) {
            $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
                $this->entry($date, $hours, $minutes, $description))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('tasks.show', [$project, $task]));
        }

        $this->assertSame(4, $task->worklogs()->count());
        $this->assertSame(480, (int) $task->worklogs()->sum('duration_minutes'));
        $this->assertSame(2, $task->worklogs()->whereDate('work_date', '2026-05-12')->count());
        $this->assertSame(0, $task->activities()->count());

        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('worklog_total_minutes', 480)
                ->where('worklogs.total', 4)
                ->has('worklogs.data', 4)
                ->where('worklogs.data.0.description', 'Refactor')
                ->where('worklogs.data.2.description', 'API integration')
                ->where('can_log_work', true)
            );
    }

    public function test_minutes_creative_flag_and_trimmed_result_are_saved(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
            $this->entry('2026-05-12', 0, 45, '  Designed the UI  ', true))
            ->assertSessionHasNoErrors();

        $entry = $task->worklogs()->sole();
        $this->assertSame(45, $entry->duration_minutes);
        $this->assertSame('Designed the UI', $entry->description);
        $this->assertTrue($entry->is_creative);
        $this->assertSame($owner->id, $entry->user_id);
    }

    public function test_zero_negative_excessive_future_and_empty_values_are_rejected(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $url = route('tasks.worklogs.store', [$project, $task]);
        $invalid = [
            [$this->entry('2026-05-12', 0, 0, 'A'), 'hours'],
            [$this->entry('2026-05-12', 24, 1, 'A'), 'hours'],
            [$this->entry('2026-05-12', -1, 0, 'A'), 'hours'],
            [$this->entry('2026-05-12', 1, 60, 'A'), 'minutes'],
            [$this->entry('2099-05-12', 1, 0, 'A'), 'work_date'],
        ];
        foreach ($invalid as [$input, $field]) {
            $this->actingAs($owner)->post($url, $input)->assertSessionHasErrors($field);
        }
        $this->assertSame(0, $task->worklogs()->count());
    }

    public function test_description_is_optional_when_creating_and_updating_worklogs(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $url = route('tasks.worklogs.store', [$project, $task]);

        // An empty description, a missing field, and whitespace-only input all store NULL.
        foreach (['', '  '] as $description) {
            $this->actingAs($owner)->post($url, $this->entry('2026-05-12', 1, 0, $description))
                ->assertSessionHasNoErrors();
        }

        $withoutDescription = $this->entry('2026-05-12', 1, 0, 'ignored');
        unset($withoutDescription['description']);
        $this->actingAs($owner)->post($url, $withoutDescription)
            ->assertSessionHasNoErrors();

        $worklogs = $task->worklogs()->orderBy('id')->get();
        $this->assertCount(3, $worklogs);
        foreach ($worklogs as $worklog) {
            $this->assertNull($worklog->description);
        }

        $worklog = $worklogs->first();
        $this->actingAs($owner)->patch(route('tasks.worklogs.update', [$project, $task, $worklog]),
            $this->entry('2026-05-13', 2, 15, '  Added a description  '))
            ->assertSessionHasNoErrors();
        $this->assertSame('Added a description', $worklog->fresh()->description);

        $this->actingAs($owner)->patch(route('tasks.worklogs.update', [$project, $task, $worklog]),
            $this->entry('2026-05-13', 2, 15, '  '))
            ->assertSessionHasNoErrors();
        $this->assertNull($worklog->fresh()->description);
        $this->assertSame(135, $worklog->fresh()->duration_minutes);

        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('worklogs.total', 3)
                ->where('worklogs.data.0.description', null));
    }

    public function test_description_above_max_length_is_rejected(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
            $this->entry('2026-05-12', 1, 0, str_repeat('a', 4001)))
            ->assertSessionHasErrors('description');

        $this->assertSame(0, $task->worklogs()->count());
    }

    public function test_work_date_uses_browser_timezone_around_utc_midnight(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->travelTo(Carbon::parse('2026-10-10 23:30:00', 'UTC'));

        try {
            // It is October 11 in Warsaw, but October 10 in UTC.
            $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
                $this->entry('2026-10-11', 0, 30, 'Late evening work') + ['timezone' => 'Europe/Warsaw'])
                ->assertSessionHasNoErrors();

            // A date that is still in the future for that user must not be accepted.
            $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
                $this->entry('2026-10-12', 0, 30, 'Future work') + ['timezone' => 'Europe/Warsaw'])
                ->assertSessionHasErrors('work_date');

            // Requests without a timezone keep the UTC default for compatibility.
            $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
                $this->entry('2026-10-11', 0, 30, 'UTC future work'))
                ->assertSessionHasErrors('work_date');

            $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
                $this->entry('2026-10-10', 1, 0, 'Invalid time zone') + ['timezone' => 'Invalid/Zone'])
                ->assertSessionHasErrors('timezone');

            $this->assertSame(1, $task->worklogs()->count());
        } finally {
            $this->travelBack();
        }
    }

    public function test_exact_minimum_and_maximum_durations_are_accepted(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $url = route('tasks.worklogs.store', [$project, $task]);

        $this->actingAs($owner)->post($url, $this->entry('2026-05-12', 0, 1, 'One minute'))
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($url, $this->entry('2026-05-12', 24, 0, 'Full day'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1441, (int) $task->worklogs()->sum('duration_minutes'));
    }

    public function test_contributor_can_log_work_but_can_only_edit_and_delete_their_own_entries(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $contributor = $this->projectContributor($project);
        $this->actingAs($contributor)->post(route('tasks.worklogs.store', [$project, $task]),
            $this->entry('2026-05-12', 1, 30, 'Contributor work'))
            ->assertSessionHasNoErrors();

        $worklog = $task->worklogs()->sole();
        $this->actingAs($owner)->patch(route('tasks.worklogs.update', [$project, $task, $worklog]),
            $this->entry('2026-05-12', 4, 0, 'Not owner'))->assertForbidden();
        $this->actingAs($owner)->delete(route('tasks.worklogs.destroy', [$project, $task, $worklog]))
            ->assertForbidden();

        $this->actingAs($contributor)->patch(route('tasks.worklogs.update', [$project, $task, $worklog]),
            $this->entry('2026-05-13', 2, 15, 'Reviewed work', true))
            ->assertSessionHasNoErrors();
        $this->assertSame(135, $worklog->fresh()->duration_minutes);
        $this->assertTrue($worklog->fresh()->is_creative);

        $this->actingAs($contributor)->delete(route('tasks.worklogs.destroy', [$project, $task, $worklog]))
            ->assertRedirect(route('tasks.show', [$project, $task]));
        $this->assertDatabaseMissing('task_worklogs', ['id' => $worklog->id]);
    }

    public function test_reader_can_view_worklogs_but_cannot_create_them(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $reader = $this->workspaceReader($project);
        $task->worklogs()->create([
            'user_id' => $owner->id, 'work_date' => '2026-05-12', 'duration_minutes' => 75,
            'description' => 'Prepared release', 'is_creative' => false,
        ]);

        $this->actingAs($reader)->get(route('tasks.show', [$project, $task]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_log_work', false)
                ->where('worklog_total_minutes', 75)
                ->where('worklogs.data.0.can_update', false)
                ->where('worklogs.data.0.can_delete', false));

        $this->actingAs($reader)->post(route('tasks.worklogs.store', [$project, $task]),
            $this->entry('2026-05-13', 1, 0, 'No access'))
            ->assertForbidden();
    }

    public function test_foreign_task_and_entry_routes_do_not_expose_or_mutate_other_project_records(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        [$otherProject, $otherOwner, $foreignTask] = $this->taskWithOwner('OTHER');
        $entry = $foreignTask->worklogs()->create([
            'user_id' => $otherOwner->id, 'work_date' => '2026-05-12',
            'duration_minutes' => 90, 'description' => 'Private work', 'is_creative' => false,
        ]);

        $this->actingAs($owner)->post(route('tasks.worklogs.store', [$otherProject, $task]),
            $this->entry('2026-05-13', 1, 0, 'Wrong project'))->assertNotFound();
        $this->actingAs($owner)->patch(route('tasks.worklogs.update', [$project, $task, $entry]),
            $this->entry('2026-05-13', 2, 0, 'Wrong entry'))->assertNotFound();
        $this->actingAs($owner)->delete(route('tasks.worklogs.destroy', [$project, $task, $entry]))
            ->assertNotFound();
        $this->assertSame(90, $entry->fresh()->duration_minutes);
    }

    public function test_worklog_is_hidden_from_outsiders_of_private_projects(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $project->update(['visibility' => ProjectVisibility::PRIVATE]);
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('tasks.show', [$project, $task]))->assertForbidden();
        $this->actingAs($stranger)->post(route('tasks.worklogs.store', [$project, $task]),
            $this->entry('2026-05-13', 1, 0, 'No access'))->assertForbidden();
    }

    public function test_pagination_does_not_limit_total_logged_minutes(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        foreach (range(1, 18) as $index) {
            $task->worklogs()->create([
                'user_id' => $owner->id,
                'work_date' => '2026-05-12',
                'duration_minutes' => 30,
                'description' => 'Work entry '.$index,
                'is_creative' => false,
            ]);
        }
        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('worklog_total_minutes', 540)
                ->where('worklogs.total', 18)
                ->has('worklogs.data', 15)
                ->where('worklogs.last_page', 2));
    }

    public function test_archived_project_displays_history_but_blocks_changes(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $worklog = $task->worklogs()->create([
            'user_id' => $owner->id,
            'work_date' => '2026-05-12',
            'duration_minutes' => 60,
            'description' => 'Existing work',
            'is_creative' => false,
        ]);
        $project->forceFill(['archived_at' => now()])->save();

        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('worklog_total_minutes', 60)
                ->where('can_log_work', false)
                ->where('worklogs.data.0.can_update', false)
                ->where('worklogs.data.0.can_delete', false));

        $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]),
            $this->entry('2026-05-13', 1, 0, 'No longer active'))->assertForbidden();
        $this->actingAs($owner)->patch(route('tasks.worklogs.update', [$project, $task, $worklog]),
            $this->entry('2026-05-13', 2, 0, 'No longer editable'))->assertForbidden();
        $this->actingAs($owner)->delete(route('tasks.worklogs.destroy', [$project, $task, $worklog]))
            ->assertForbidden();
        $this->assertSame(60, $worklog->fresh()->duration_minutes);
    }

    /** @return array<string, mixed> */
    private function entry(string $date, int $hours, int $minutes, string $description, bool $creative = false): array
    {
        return ['work_date' => $date, 'hours' => $hours, 'minutes' => $minutes,
            'description' => $description, 'is_creative' => $creative];
    }

    /** @return array{Project, User, Task} */
    private function taskWithOwner(string $key = 'APP'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER,
        ]);
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Project '.$key, $key);
        return [$project, $owner, app(CreateTask::class)->handle($owner, $project, 'Worklog task')];
    }

    private function workspaceReader(Project $project): User
    {
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $reader->id, 'role' => WorkspaceRole::MEMBER,
        ]);
        return $reader;
    }

    private function projectContributor(Project $project): User
    {
        $contributor = $this->workspaceReader($project);
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $contributor->id, 'role' => ProjectRole::MEMBER,
        ]);
        return $contributor;
    }
}
