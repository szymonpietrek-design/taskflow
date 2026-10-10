<?php

namespace Tests\Feature\Seeders;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseChangelog;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Releases\ReleaseChangelogContext;
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
        $this->assertCount(6, $payload['tasks']);
        $this->assertTrue(collect($payload['tasks'])->contains(
            fn (array $row): bool => collect($row['messages'])->contains(
                fn (array $message): bool => str_contains($message['text'], 'Raport CSV'),
            ),
        ));

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
        ];
        $task = Task::query()->whereHas('project', fn ($q) => $q->where('key', 'NSTAR'))->where('number', 2)->sole();
        $task->update(['title' => 'Manually edited demo title']);
        $owner = User::query()->where('email', 'demo.owner@taskflow.test')->sole();
        $oldHash = $owner->password;
        $owner->update(['name' => 'Renamed demo owner']);

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
        ]);
        $this->assertSame('Manually edited demo title', $task->fresh()->title);
        $this->assertSame('Renamed demo owner', $owner->fresh()->name);
        $this->assertSame($oldHash, $owner->fresh()->password);
        $this->assertTrue(Hash::check('OnlyForLocalTests2026!', $owner->fresh()->password));
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
