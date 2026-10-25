<?php

namespace Tests\Feature\Reports;

use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\Report;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryCommit;
use App\Models\RepositoryConnection;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\Workspace;
use App\Support\Reports\ReportGenerationContext;
use App\Support\Reports\ReportSourceOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportVcsEvidenceDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_commit_message_details_and_current_pr_file_metadata_without_patches(): void
    {
        [$user, $project, $repository] = $this->repositoryFixture();
        RepositoryCommit::query()->create([
            'repository_connection_id' => $repository->id,
            'sha' => str_repeat('a', 40),
            'message' => "feat(vcs): add multi-provider settings\n\nAdd provider-neutral models and profile controls.",
            'committed_at' => '2026-10-12 12:00:00',
        ]);
        $pull = $this->createPull($repository, 4);
        $pull->forceFill([
            'head_sha' => str_repeat('b', 40),
            'files_head_sha' => str_repeat('b', 40),
            'files_synced_at' => '2026-10-20 12:00:00',
            'remote_updated_at' => '2026-10-19 12:00:00',
            'target_branch' => 'main',
        ])->save();
        for ($i = 1; $i <= 14; $i++) {
            $path = 'app/Services/Module'.$i.'.php';
            $pull->files()->create([
                'path' => $path,
                'path_hash' => hash('sha256', $path),
                'status' => 'modified',
                'additions' => 4,
                'deletions' => 2,
                'changes' => 6,
                'patch' => 'CONFIDENTIAL_DIFF_SHOULD_NOT_LEAVE_THE_SERVER',
                'patch_truncated' => false,
            ]);
        }
        $report = $this->report($user, $project, ['vcs']);
        $context = app(ReportGenerationContext::class)->build($report);
        $vcs = json_decode($context, true, 512, JSON_THROW_ON_ERROR)['vcs'];

        $this->assertSame('Add provider-neutral models and profile controls.', $vcs['commits'][0]['details']);
        $this->assertSame('main', $vcs['pull_requests'][0]['target_branch']);
        $this->assertTrue($vcs['pull_requests'][0]['merged_in_period']);
        $this->assertSame('current', $vcs['pull_requests'][0]['files_snapshot']);
        $this->assertSame(14, $vcs['pull_requests'][0]['file_count']);
        $this->assertSame(12, $vcs['pull_requests'][0]['files_included']);
        $this->assertCount(12, $vcs['pull_requests'][0]['files']);
        $this->assertSame('app/Services/Module1.php', $vcs['pull_requests'][0]['files'][0]['path']);
        $this->assertStringNotContainsString('CONFIDENTIAL_DIFF_SHOULD_NOT_LEAVE_THE_SERVER', $context);
        $this->assertStringNotContainsString('PRIVATE_PR_BODY_SHOULD_NOT_LEAVE_THE_SERVER', $context);
    }

    public function test_stale_or_out_of_period_pr_files_are_not_submitted_to_ai(): void
    {
        [$user, $project, $repository] = $this->repositoryFixture();
        $pull = $this->createPull($repository, 5);
        $pull->forceFill([
            'head_sha' => str_repeat('b', 40),
            'files_head_sha' => str_repeat('a', 40),
            'files_synced_at' => '2026-10-14 12:00:00',
            'remote_updated_at' => '2026-10-14 12:00:00',
        ])->save();
        $path = 'private/not-relevant.php';
        $pull->files()->create([
            'path' => $path,
            'path_hash' => hash('sha256', $path),
            'status' => 'added',
            'additions' => 5,
            'deletions' => 0,
            'changes' => 5,
            'patch' => null,
            'patch_truncated' => false,
        ]);
        $report = $this->report($user, $project, ['vcs']);

        $vcs = json_decode(app(ReportGenerationContext::class)->build($report), true, 512, JSON_THROW_ON_ERROR)['vcs'];
        $this->assertSame('unavailable_or_stale', $vcs['pull_requests'][0]['files_snapshot']);
        $this->assertNull($vcs['pull_requests'][0]['file_count']);
        $this->assertSame([], $vcs['pull_requests'][0]['files']);

        $pull->forceFill([
            'files_head_sha' => str_repeat('b', 40),
            'remote_updated_at' => '2026-11-02 12:00:00',
        ])->save();
        $context = app(ReportGenerationContext::class)->build($report);
        $this->assertStringNotContainsString($path, $context);
    }

    public function test_open_task_does_not_hide_committed_work_and_open_pr_is_not_merged(): void
    {
        [$user, $project, $repository] = $this->repositoryFixture();
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'completed_at' => null,
        ]);
        // Keep the fixture in the report window even when tests run later.
        $task->forceFill(['created_at' => '2026-10-18 12:00:00'])->save();
        RepositoryCommit::query()->create([
            'repository_connection_id' => $repository->id,
            'sha' => str_repeat('e', 40),
            'message' => 'feat(vcs): add settings for multiple Git providers',
            'committed_at' => '2026-10-21 12:00:00',
        ]);
        $pull = $this->createPull($repository, 8);
        $pull->forceFill([
            'state' => 'open',
            'merged_at' => null,
            'target_branch' => 'develop',
        ])->save();

        $data = json_decode(app(ReportGenerationContext::class)->build(
            $this->report($user, $project, ['tasks', 'vcs']),
        ), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $data['tasks']);
        $this->assertSame($project->key.'-'.$task->number, $data['tasks'][0]['reference']);
        $this->assertFalse($data['tasks'][0]['completed_in_period']);
        $this->assertSame('feat(vcs): add settings for multiple Git providers', $data['vcs']['commits'][0]['subject']);
        $this->assertArrayNotHasKey('branch', $data['vcs']['commits'][0]);
        $this->assertSame('open', $data['vcs']['pull_requests'][0]['state']);
        $this->assertNull($data['vcs']['pull_requests'][0]['merged_at']);
        $this->assertFalse($data['vcs']['pull_requests'][0]['merged_in_period']);
        $this->assertSame('develop', $data['vcs']['pull_requests'][0]['target_branch']);
    }

    public function test_pr_merged_after_report_period_is_not_counted_as_in_period_integration(): void
    {
        [$user, $project, $repository] = $this->repositoryFixture();
        $pull = $this->createPull($repository, 12);
        $pull->forceFill([
            'merged_at' => '2026-10-28 12:00:00',
            'target_branch' => 'main',
        ])->save();

        $vcs = json_decode(app(ReportGenerationContext::class)->build(
            $this->report($user, $project, ['vcs']),
        ), true, 512, JSON_THROW_ON_ERROR)['vcs'];

        $this->assertCount(1, $vcs['pull_requests']);
        $this->assertSame('merged', $vcs['pull_requests'][0]['state']);
        $this->assertSame('2026-10-28', $vcs['pull_requests'][0]['merged_at']);
        $this->assertFalse($vcs['pull_requests'][0]['merged_in_period']);
    }

    public function test_existing_task_changed_within_period_is_included_even_if_created_long_before(): void
    {
        [$user, $project] = $this->repositoryFixture();
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'created_at' => '2026-08-01 12:00:00',
            'completed_at' => null,
        ]);
        // Factories may override created_at via timestamps, so force a historical date.
        $task->forceFill(['created_at' => '2026-08-01 12:00:00'])->save();
        $activity = $task->activities()->create([
            'user_id' => $user->id,
            'type' => \App\Enums\TaskActivityType::DESCRIPTION_CHANGED,
            'data' => ['to' => 'DO_NOT_SEND_FULL_ACTIVITY_SNAPSHOT'],
        ]);
        $activity->forceFill(['created_at' => '2026-10-13 12:00:00'])->save();
        $report = $this->report($user, $project, ['tasks']);

        $data = json_decode(app(ReportGenerationContext::class)->build($report), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['tasks']);
        $this->assertTrue($data['tasks'][0]['changed_in_period']);
        $this->assertFalse($data['tasks'][0]['completed_in_period']);
        $this->assertSame(1, app(ReportSourceOverview::class)->forReport($report)['tasks']);
        $this->assertStringNotContainsString('DO_NOT_SEND_FULL_ACTIVITY_SNAPSHOT', json_encode($data));
    }

    private function createPull(RepositoryConnection $repository, int $number): RepositoryChangeRequest
    {
        return RepositoryChangeRequest::query()->create([
            'repository_connection_id' => $repository->id,
            'external_change_request_id' => (string) $number,
            'number' => $number,
            'title' => 'Improve Git integrations',
            'body' => 'PRIVATE_PR_BODY_SHOULD_NOT_LEAVE_THE_SERVER',
            'web_url' => 'https://example.test/demo/pulls/'.$number,
            'state' => 'merged',
            'merged_at' => '2026-10-19 12:00:00',
            'remote_created_at' => '2026-10-17 12:00:00',
        ]);
    }

    private function report(User $user, Project $project, array $sources): Report
    {
        return Report::query()->create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'title' => 'October summary',
            'format' => 'project_summary',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-25',
            'sources' => $sources,
        ]);
    }

    /** @return array{User,Project,RepositoryConnection} */
    private function repositoryFixture(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $user->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $user->id]);
        $connection = VcsConnection::query()->create([
            'vcs_instance_id' => GitHubInstance::get()->id,
            'workspace_id' => $workspace->id,
            'external_connection_id' => '123',
            'account_login' => 'demo',
            'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => 'repo1',
            'full_name' => 'demo/repo1',
            'default_branch' => 'main',
            'start_sha' => str_repeat('c', 40),
            'last_synced_sha' => str_repeat('c', 40),
            'connected_at' => now(),
        ]);
        return [$user, $project, $repository];
    }
}
