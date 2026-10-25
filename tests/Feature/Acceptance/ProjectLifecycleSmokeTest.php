<?php

namespace Tests\Feature\Acceptance;

use App\Actions\Projects\CreateProject;
use App\Contracts\Ai\ReportTextGenerator;
use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\TaskType;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectLifecycleSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_complete_work_and_publish_a_report_without_external_ai_calls(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = app(CreateProject::class)->handle(
            $owner,
            $workspace,
            'Acceptance Application',
            'ACC',
            ProjectType::APPLICATION,
        );
        $todo = $project->taskStatuses()->where('category', TaskStatusCategory::TODO->value)->firstOrFail();
        $done = $project->taskStatuses()->where('category', TaskStatusCategory::DONE->value)->firstOrFail();

        // Follow the same HTTP endpoints used by the Inertia/Vue application.
        $this->actingAs($owner)->post(route('tasks.store', $project), [
            'title' => 'Implement notifications',
            'description' => 'Notify users about completed builds',
            'type' => TaskType::FEATURE->value,
            'priority' => TaskPriority::HIGH->value,
            'status_id' => $todo->id,
        ])->assertSessionHasNoErrors();
        $task = $project->tasks()->sole();
        $this->assertSame('ACC-1', $task->reference());

        $workDate = now()->toDateString();
        $this->actingAs($owner)->post(route('tasks.worklogs.store', [$project, $task]), [
            'work_date' => $workDate,
            'hours' => 1,
            'minutes' => 30,
            'description' => 'Implemented notification delivery',
            'is_creative' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame(90, (int) $task->worklogs()->sum('duration_minutes'));

        $this->actingAs($owner)->patch(route('tasks.status.update', [$project, $task]), [
            'status_id' => $done->id,
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($owner)->post(route('releases.store', $project), [
            'version' => '1.0.0',
            'name' => 'Acceptance release',
            'include_completed_tasks' => true,
        ])->assertSessionHasNoErrors();
        $release = $project->releases()->sole();
        $this->assertSame($release->id, $task->fresh()->release_id);

        $this->actingAs($owner)->post(route('reports.store', $project), [
            'title' => 'Acceptance report',
            'format' => 'project_summary',
            'period_start' => $workDate,
            'period_end' => $workDate,
            'sources' => ['tasks', 'worklogs'],
        ])->assertSessionHasNoErrors();
        $report = $project->reports()->sole();

        $seenContext = null;
        $generator = function (string $context, string $language) use (&$seenContext): string {
            $seenContext = json_decode($context, true, 512, JSON_THROW_ON_ERROR);

            return "## Podsumowanie\n\nZaimplementowano powiadomienia i zarejestrowano czas pracy.";
        };
        $this->app->instance(ReportTextGenerator::class, new class($generator) implements ReportTextGenerator
        {
            public function __construct(private readonly \Closure $callback) {}

            public function generate(string $context, string $language): string
            {
                return ($this->callback)($context, $language);
            }
        });

        $this->actingAs($owner)->post(route('reports.generate', [$project, $report]), [
            'language' => 'pl',
        ])->assertSessionHasNoErrors();
        $this->assertSame(90, $seenContext['worklogs']['total_minutes']);
        $this->assertSame('project_summary', $seenContext['report_format']);
        $this->assertStringContainsString('Zaimplementowano powiadomienia', $report->fresh()->body);

        $this->actingAs($owner)->post(route('reports.publish', [$project, $report]))
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('reports.share.store', [$project, $report]))
            ->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertNotNull($report->published_at);
        $this->assertNotNull($report->share_token);
        $publicUrl = route('reports.public', ['report' => $report, 'token' => $report->share_token]);

        auth()->logout();
        $this->get($publicUrl)->assertOk()->assertSee('Zaimplementowano powiadomienia');
        $this->get(route('reports.show', [$project, $report]))->assertRedirect(route('login'));

        $this->actingAs($owner)->delete(route('reports.share.destroy', [$project, $report]))
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->get($publicUrl)->assertNotFound();
    }
}
