<?php

namespace Tests\Feature\Reports;

use App\Enums\TaskActivityType;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Support\Reports\ReportGenerationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportActivityContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_description_snapshots_are_counted_but_never_sent_to_ai(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $user->id]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status_id' => $status->id, 'created_by' => $user->id]);
        $otherProject = Project::factory()->create(['created_by' => $user->id]);
        $otherStatus = TaskStatus::factory()->create(['project_id' => $otherProject->id]);
        $otherTask = Task::factory()->create(['project_id' => $otherProject->id, 'status_id' => $otherStatus->id, 'created_by' => $user->id]);
        $report = Report::query()->create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'format' => 'project_summary',
            'title' => 'Test',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-25',
            'sources' => ['activity'],
        ]);
        foreach (range(1, 8) as $i) {
            $entry = $task->activities()->create([
                'user_id' => $user->id,
                'type' => TaskActivityType::DESCRIPTION_CHANGED,
                'data' => ['from' => 'PRIVATE TEXT BEFORE', 'to' => 'PRIVATE TEXT AFTER'.str_repeat('!', 250)],
            ]);
            $entry->forceFill(['created_at' => '2026-10-12 12:00:00'])->save();
        }
        $entry = $task->activities()->create([
            'user_id' => $user->id,
            'type' => TaskActivityType::STATUS_CHANGED,
            'data' => ['from' => ['name' => 'Do zrobienia', 'id' => 5], 'to' => ['name' => 'W trakcie', 'id' => 7]],
        ]);
        $entry->forceFill(['created_at' => '2026-10-13 12:00:00'])->save();
        $entry = $otherTask->activities()->create([
            'user_id' => $user->id,
            'type' => TaskActivityType::DESCRIPTION_CHANGED,
            'data' => ['to' => 'FOREIGN SECRET'],
        ]);
        $entry->forceFill(['created_at' => '2026-10-14 12:00:00'])->save();
        $context = app(ReportGenerationContext::class)->build($report);
        $activity = json_decode($context, true, 512, JSON_THROW_ON_ERROR)['activity'];
        $this->assertSame(9, $activity['total_events']);
        $this->assertSame(8, $activity['counts_by_type']['DESCRIPTION_CHANGED']);
        $this->assertSame(1, $activity['counts_by_type']['STATUS_CHANGED']);
        $this->assertCount(1, $activity['notable_events']);
        $this->assertSame('W trakcie', $activity['notable_events'][0]['to']);
        $this->assertStringNotContainsString('PRIVATE TEXT', $context);
        $this->assertStringNotContainsString('FOREIGN SECRET', $context);
    }
}
