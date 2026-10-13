<?php

namespace Tests\Feature\Seeders;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseChangelog;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskWorklog;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Releases\ReleaseChangelogContext;
use App\Support\Reports\ReportSourceOverview;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('demo.password', 'OnlyForLocalTests2026!');
        $this->withoutVite();
    }

    public function test_it_creates_a_realistic_demo_with_multilingual_ai_source_material(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);

        $workspace = Workspace::query()->where('slug', 'taskflow-demo')->sole();
        $app = Project::query()->where('workspace_id', $workspace->id)->where('key', 'NSTAR')->sole();
        $site = Project::query()->where('workspace_id', $workspace->id)->where('key', 'STUDIO')->sole();
        $this->assertSame(ProjectType::APPLICATION, $app->type);
        $this->assertSame(ProjectType::STANDARD, $site->type);
        $this->assertSame(3, $workspace->memberships()->count());
        $this->assertSame(3, $app->memberships()->count());
        $this->assertSame(18, $app->tasks()->count());
        $this->assertSame(5, $site->tasks()->count());
        $this->assertSame(18, $app->task_sequence);
        $this->assertSame(5, $site->task_sequence);
        $this->assertSame(3, $app->releases()->count());
        $this->assertGreaterThanOrEqual(20, TaskMessage::query()->count());
        $this->assertGreaterThanOrEqual(30, TaskActivity::query()->count());
        $this->assertSame(33, TaskWorklog::query()->count());
        $this->assertSame(3, Report::query()->count());
        $this->assertSame(2, $app->reports()->count());
        $this->assertSame(1, $site->reports()->count());

        // The sample intentionally contains split days across different tasks/projects.
        $developer = User::query()->where('email', 'demo.developer@taskflow.test')->sole();
        $baseDate = $workspace->created_at->toImmutable()->startOfDay();
        foreach ([44, 58] as $day) {
            $this->assertSame(480, (int) TaskWorklog::query()
                ->where('user_id', $developer->id)
                ->whereDate('work_date', $baseDate->addDays($day)->toDateString())
                ->sum('duration_minutes'));
        }

        // Every user's DAILY total must respect the same limit across both projects.
        $dailyTotals = TaskWorklog::query()->select('user_id', 'work_date')
            ->selectRaw('SUM(duration_minutes) AS logged_minutes')
            ->groupBy('user_id', 'work_date')->get();
        foreach ($dailyTotals as $dailyTotal) {
            $this->assertLessThanOrEqual(480, (int) $dailyTotal->logged_minutes);
        }

        $report = $app->reports()->where('title', 'Northstar · Last 4 weeks')->sole();
        $this->assertSame(['tasks', 'messages', 'activity', 'worklogs'], $report->sources);
        $overview = app(ReportSourceOverview::class)->forReport($report);
        $this->assertSame(21, $overview['worklogs']['entries']);
        $this->assertSame(3190, $overview['worklogs']['total_minutes']);
        $this->assertSame(2000, app(ReportSourceOverview::class)->forReport(
            $site->reports()->sole(),
        )['worklogs']['total_minutes']);

        $published = $app->releases()->where('version', '1.0.0')->sole();
        $planned = $app->releases()->where('version', '1.1.0')->sole();
        $draft = $app->releases()->where('version', '1.2.0')->sole();
        $this->assertSame(ReleaseStatus::RELEASED, $published->status);
        $this->assertSame(ReleaseStatus::PLANNED, $planned->status);
        $this->assertSame(ReleaseStatus::DRAFT, $draft->status);
        $this->assertNotNull($published->changelog?->published_at);
        $this->assertSame('en', $published->changelog?->published_locale);
        $this->assertNull($planned->changelog);
        $this->assertGreaterThan(0, $planned->tasks()->count());

        $payload = json_decode(app(ReleaseChangelogContext::class)->build($planned), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('1.1.0', $payload['release']);
        // Only finished tasks are eligible as source material for an AI changelog.
        // The other four tasks in release 1.1.0 are still in progress or planned.
        $this->assertTrue($payload['only_completed_tasks']);
        $this->assertSame(['NSTAR-9', 'NSTAR-12'], array_column($payload['tasks'], 'reference'));
        $messages = collect($payload['tasks'])
            ->flatMap(fn (array $row): array => $row['messages'])
            ->pluck('text')
            ->implode(' ');
        $this->assertStringContainsString('Widok historii', $messages);
        $this->assertStringContainsString('Testy logowania', $messages);
        // The CSV report task is unfinished, so its discussion must not reach the AI.
        $this->assertStringNotContainsString('Raport CSV', $messages);

        $followup = $app->tasks()->where('number', 18)->sole();
        $this->assertNull($followup->parent_id);
        $this->assertNotNull($followup->source_message_id);
        $this->assertSame($app->tasks()->where('number', 7)->sole()->id, $followup->source_task_id);
        $this->assertTrue($followup->sourceMessage->created_at->lt($followup->created_at));
        $this->assertTrue($published->released_at->gt($app->tasks()->where('number', 1)->sole()->completed_at));
    }

    public function test_repeated_seeding_does_not_duplicate_or_overwrite_edited_data(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);
        $snapshot = [
            User::query()->count(),
            Workspace::query()->count(),
            Project::query()->count(),
            Task::query()->count(),
            TaskMessage::query()->count(),
            TaskActivity::query()->count(),
            Release::query()->count(),
            ReleaseChangelog::query()->count(),
            TaskWorklog::query()->count(),
            Report::query()->count(),
        ];
        $task = Task::query()->whereHas('project', fn ($q) => $q->where('key', 'NSTAR'))->where('number', 2)->sole();
        $task->update(['title' => 'Manually edited demo title']);
        $owner = User::query()->where('email', 'demo.owner@taskflow.test')->sole();
        $oldHash = $owner->password;
        $owner->update(['name' => 'Renamed demo owner']);
        $worklog = TaskWorklog::query()->orderBy('id')->firstOrFail();
        $worklog->update(['description' => 'Edited by the user']);
        $report = Report::query()->orderBy('id')->firstOrFail();
        $report->update(['title' => 'Custom report title']);

        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);

        $this->assertSame($snapshot, [
            User::query()->count(),
            Workspace::query()->count(),
            Project::query()->count(),
            Task::query()->count(),
            TaskMessage::query()->count(),
            TaskActivity::query()->count(),
            Release::query()->count(),
            ReleaseChangelog::query()->count(),
            TaskWorklog::query()->count(),
            Report::query()->count(),
        ]);
        $this->assertSame('Manually edited demo title', $task->fresh()->title);
        $this->assertSame('Renamed demo owner', $owner->fresh()->name);
        $this->assertSame($oldHash, $owner->fresh()->password);
        $this->assertSame('Edited by the user', $worklog->fresh()->description);
        $this->assertSame('Custom report title', $report->fresh()->title);
        $this->assertTrue(Hash::check('OnlyForLocalTests2026!', $owner->fresh()->password));
    }

    public function test_reseeding_preserves_manually_assigned_releases_on_demo_tasks(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);

        $app = Project::query()->where('key', 'NSTAR')->sole();
        $release = $app->releases()->where('version', '1.1.0')->sole();
        $unassignedTask = $app->tasks()->where('number', 15)->sole();
        $previouslyAssignedTask = $app->tasks()->where('number', 1)->sole();
        $this->assertNull($unassignedTask->release_id);

        // Task #15 has no release in DemoScenario; #1 originally belongs to 1.0.0.
        $unassignedTask->update(['release_id' => $release->id]);
        $previouslyAssignedTask->update(['release_id' => $release->id]);
        $activityCount = TaskActivity::query()->count();

        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);

        $this->assertSame($release->id, (int) $unassignedTask->fresh()->release_id);
        $this->assertSame($release->id, (int) $previouslyAssignedTask->fresh()->release_id);
        $this->assertSame($activityCount, TaskActivity::query()->count());
        $this->assertSame(0, $unassignedTask->activities()
            ->where('type', TaskActivityType::RELEASE_CHANGED->value)->count());
    }

    public function test_reseeding_does_not_exceed_daily_limit_after_a_manual_entry(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);
        $developer = User::query()->where('email', 'demo.developer@taskflow.test')->sole();
        $workspace = Workspace::query()->where('slug', 'taskflow-demo')->sole();
        $day = $workspace->created_at->toImmutable()->startOfDay()->addDays(44)->toDateString();
        $app = Project::query()->where('workspace_id', $workspace->id)->where('key', 'NSTAR')->sole();
        $task = $app->tasks()->where('number', 7)->sole();
        $worklog = $task->worklogs()->where('user_id', $developer->id)
            ->whereDate('work_date', $day)->sole();
        $worklog->delete();

        // The missing 90-minute demo entry has been replaced by a manually added worklog.
        $task->worklogs()->create([
            'user_id' => $developer->id,
            'work_date' => $day,
            'duration_minutes' => 90,
            'description' => 'Manual work on this date',
            'is_creative' => false,
        ]);
        $beforeCount = TaskWorklog::query()->count();

        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertExitCode(0);

        $this->assertSame($beforeCount, TaskWorklog::query()->count());
        $this->assertSame(480, (int) TaskWorklog::query()->where('user_id', $developer->id)
            ->whereDate('work_date', $day)->sum('duration_minutes'));
        $this->assertSame(0, $task->worklogs()->where('created_at', $worklog->created_at)->count());
    }

    public function test_it_requires_a_local_password_before_changing_the_database(): void
    {
        config()->set('demo.password', null);
        try {
            (new DemoSeeder)->run();
            $this->fail('The seeder must reject a missing demo password.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('TASKFLOW_DEMO_PASSWORD', $exception->getMessage());
        }

        $this->assertSame(0, Workspace::query()->count());
        $this->assertSame(0, User::query()->count());
    }
}
