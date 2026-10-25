<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Enums\ProjectType;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Enums\ReportFormat;
use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\Release;
use App\Models\Report;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryCommit;
use App\Models\RepositoryConnection;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\Workspace;
use App\Support\Releases\ReleaseChangelogContext;
use App\Support\Reports\ReportGenerationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class VcsReportReleaseEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_vcs_is_explicit_and_scoped_to_project_and_period(): void
    {
        [$user, $project, $repository] = $this->repositoryFixture();
        $other = Project::factory()->create(['workspace_id' => $project->workspace_id, 'created_by' => $user->id]);
        $foreign = RepositoryConnection::query()->create([
            'project_id' => $other->id, 'vcs_connection_id' => $repository->vcs_connection_id,
            'external_repository_id' => 'other', 'full_name' => 'demo/other', 'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40), 'last_synced_sha' => str_repeat('a', 40), 'connected_at' => now(),
        ]);
        RepositoryCommit::query()->create(['repository_connection_id' => $repository->id, 'sha' => str_repeat('b', 40),
            'message' => "Improve pagination\nImprove cursor handling and page boundaries.", 'committed_at' => '2026-10-05 12:00:00']);
        RepositoryCommit::query()->create(['repository_connection_id' => $foreign->id, 'sha' => str_repeat('c', 40),
            'message' => 'FOREIGN PROJECT SECRET', 'committed_at' => '2026-10-05 12:00:00']);
        RepositoryCommit::query()->create(['repository_connection_id' => $repository->id, 'sha' => str_repeat('d', 40),
            'message' => 'Outside period', 'committed_at' => '2026-09-01 12:00:00']);
        RepositoryChangeRequest::query()->create([
            'repository_connection_id' => $repository->id, 'external_change_request_id' => '45', 'number' => 45,
            'title' => 'Fix pagination', 'body' => 'PRIVATE BODY', 'web_url' => 'https://example.test/demo/pulls/45',
            'state' => 'merged', 'merged_at' => '2026-10-05 12:00:00',
        ]);
        $report = Report::query()->create([
            'project_id' => $project->id, 'created_by' => $user->id, 'title' => 'October report',
            'format' => ReportFormat::PROJECT_SUMMARY, 'period_start' => '2026-10-01', 'period_end' => '2026-10-07',
            'sources' => ['tasks'],
        ]);
        $without = json_decode(app(ReportGenerationContext::class)->build($report), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('vcs', $without);
        $report->update(['sources' => ['vcs']]);
        $with = json_decode(app(ReportGenerationContext::class)->build($report), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $with['vcs']['commit_count']);
        $this->assertSame(1, $with['vcs']['pull_request_count']);
        $this->assertSame('Improve pagination', $with['vcs']['commits'][0]['subject']);
        $this->assertSame(45, $with['vcs']['pull_requests'][0]['number']);
        $this->assertStringNotContainsString('PRIVATE BODY', json_encode($with));
        $this->assertStringNotContainsString('FOREIGN', json_encode($with));
        // Commit descriptions are now intentionally available to selected VCS reports.
        // PR bodies remain excluded; never send raw code patches.
        $this->assertSame('Improve cursor handling and page boundaries.', $with['vcs']['commits'][0]['details']);
        $this->assertCount(1, $with['vcs']['commits']);
        $report->update(['contributor_ids' => [$user->id]]);
        $this->expectException(InvalidArgumentException::class);
        app(ReportGenerationContext::class)->build($report);
    }

    public function test_release_vcs_only_includes_matching_completed_task_references(): void
    {
        [$user, $project, $repository] = $this->repositoryFixture();
        $project->update(['type' => ProjectType::APPLICATION]);
        $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $status->update(['category' => TaskStatusCategory::DONE]);
        Task::factory()->create(['project_id' => $project->id, 'release_id' => $release->id,
            'status_id' => $status->id, 'created_by' => $user->id, 'number' => 7,
            'completed_at' => '2026-10-05 12:00:00']);
        $reference = $project->key.'-7';
        RepositoryCommit::query()->create(['repository_connection_id' => $repository->id, 'sha' => str_repeat('e', 40),
            'message' => 'Fix '.$reference, 'committed_at' => '2026-10-05 12:00:00']);
        RepositoryCommit::query()->create(['repository_connection_id' => $repository->id, 'sha' => str_repeat('f', 40),
            'message' => 'Unrelated work', 'committed_at' => '2026-10-05 12:00:00']);
        $without = json_decode(app(ReleaseChangelogContext::class)->build($release), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('vcs', $without);
        $with = json_decode(app(ReleaseChangelogContext::class)->build($release, true), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $with['vcs']['commits']);
        $this->assertStringContainsString($reference, $with['vcs']['commits'][0]['subject']);
        $this->assertStringNotContainsString('Unrelated work', json_encode($with));
    }

    /** @return array{User,Project,RepositoryConnection} */
    private function repositoryFixture(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $user->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $user->id]);
        // The VCS cutover migration already creates the trusted github.com instance.
        // Reuse its canonical identity instead of violating the unique provider/key index.
        $instance = GitHubInstance::get();
        $connection = VcsConnection::query()->create(['vcs_instance_id' => $instance->id, 'workspace_id' => $workspace->id,
            'external_connection_id' => '123', 'account_login' => 'demo', 'connected_at' => now()]);
        $repository = RepositoryConnection::query()->create(['project_id' => $project->id,
            'vcs_connection_id' => $connection->id, 'external_repository_id' => 'repo1',
            'full_name' => 'demo/repo1', 'default_branch' => 'main', 'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('a', 40), 'connected_at' => now()]);
        return [$user, $project, $repository];
    }
}
