<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Ai\PullRequestAiAnalysisHistory;
use App\Integrations\Vcs\Ai\QueuePullRequestAiAnalysis;
use App\Integrations\Vcs\Ai\RecoverStalledPullRequestAiAnalyses as Recovery;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Jobs\GeneratePullRequestAiAnalysisJob;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecoverStalledPullRequestAiAnalysesTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_preview_does_not_modify_rows_or_send_anything_to_ai(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pending = $this->analysis($project, $pull, $actor, 'pending', 20);
        $running = $this->analysis($project, $pull, $actor, 'running', 20);

        $this->artisan('vcs:recover-ai-analyses', ['--project' => (string) $project->id])
            ->expectsOutput('Stalled pending: 1')
            ->expectsOutput('Stalled running: 1')
            ->expectsOutput('Marked failed: 0')
            ->assertSuccessful();

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('running', $running->fresh()->status);
        $this->assertNull($pending->fresh()->finished_at);
    }

    public function test_apply_marks_stale_rows_failed_without_retrying_ai(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pending = $this->analysis($project, $pull, $actor, 'pending', 20);
        $running = $this->analysis($project, $pull, $actor, 'running', 20);
        $completed = $this->analysis($project, $pull, $actor, 'completed', 20);

        $result = app(Recovery::class)->run($project->id, true);
        $this->assertSame(['pending' => 1, 'running' => 1, 'recovered' => 2, 'scanned' => 2], $result);
        $this->assertSame('queue_timeout', $pending->fresh()->error_code);
        $this->assertSame('outcome_unknown', $running->fresh()->error_code);
        $this->assertSame('failed', $running->fresh()->status);
        $this->assertNotNull($running->fresh()->finished_at);
        $this->assertSame('completed', $completed->fresh()->status);
        $this->assertSame(0, app(Recovery::class)->run($project->id, true)['recovered']);

        // Old, late queue deliveries cannot execute the failed row again.
        $job = new GeneratePullRequestAiAnalysisJob($running->id);
        $job->handle(app(\App\Integrations\Vcs\Ai\GeneratePullRequestAiAnalysis::class));
        $this->assertSame('failed', $running->fresh()->status);
    }

    public function test_recent_pending_and_running_analyses_are_not_recovered(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pending = $this->analysis($project, $pull, $actor, 'pending', 2);
        $running = $this->analysis($project, $pull, $actor, 'running', 2);

        $this->assertSame(0, app(Recovery::class)->run($project->id, true)['scanned']);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('running', $running->fresh()->status);
    }

    public function test_project_scope_and_limit_prevent_touching_other_projects(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        [$otherProject, $otherPull, $otherActor] = $this->fixture();
        $first = $this->analysis($project, $pull, $actor, 'pending', 20);
        $second = $this->analysis($project, $pull, $actor, 'running', 20);
        $unrelated = $this->analysis($otherProject, $otherPull, $otherActor, 'pending', 20);

        $result = app(Recovery::class)->run($project->id, true, 1);
        $this->assertSame(1, $result['scanned']);
        $this->assertSame(1, $result['recovered']);
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame('running', $second->fresh()->status);
        $this->assertSame('pending', $unrelated->fresh()->status);
    }

    public function test_a_failed_analysis_can_only_be_resubmitted_through_fresh_consent(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $first = $this->analysis($project, $pull, $actor, 'pending', 20);
        app(Recovery::class)->run($project->id, true);
        $this->assertSame('queue_timeout', $first->fresh()->error_code);

        $pull->forceFill([
            'synced_at' => now(),
            'files_synced_at' => now(),
            'files_head_sha' => self::SHA,
        ])->save();
        $pull->files()->create([
            'path' => 'src/Example.php',
            'path_hash' => hash('sha256', 'src/Example.php'),
            'status' => 'modified',
            'additions' => 1,
            'deletions' => 1,
            'changes' => 2,
            'patch' => "@@ -1 +1 @@\n-old\n+new",
            'patch_truncated' => false,
        ]);

        Queue::fake();
        $url = route('projects.github.pull.ai.analyze', [$project, 10]);
        $payload = [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => false, 'approve_code_sharing' => false,
        ];
        $this->actingAs($actor)->post($url, $payload)
            ->assertSessionHasErrors('approve_external_sharing');
        Queue::assertNotPushed(GeneratePullRequestAiAnalysisJob::class);

        $payload['approve_external_sharing'] = true;
        $this->actingAs($actor)->post($url, $payload)->assertRedirect();
        Queue::assertPushed(GeneratePullRequestAiAnalysisJob::class, 1);
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame([2, 1], PullRequestAiAnalysis::query()
            ->where('project_id', $project->id)
            ->orderByDesc('version')->pluck('version')->all());
    }

    public function test_bad_options_are_rejected_without_writes(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pending = $this->analysis($project, $pull, $actor, 'pending', 20);
        $this->artisan('vcs:recover-ai-analyses', ['--project' => '../2', '--apply' => true])->assertExitCode(2);
        $this->artisan('vcs:recover-ai-analyses', ['--limit' => '0', '--apply' => true])->assertExitCode(2);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_timeout_callback_distinguishes_unstarted_and_unknown_outcome_and_preserves_completed(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pending = $this->analysis($project, $pull, $actor, 'pending', 1);
        $running = $this->analysis($project, $pull, $actor, 'running', 1);
        $complete = $this->analysis($project, $pull, $actor, 'completed', 1);

        (new GeneratePullRequestAiAnalysisJob($pending->id))->failed(null);
        (new GeneratePullRequestAiAnalysisJob($running->id))->failed(null);
        (new GeneratePullRequestAiAnalysisJob($complete->id))->failed(null);

        $this->assertSame('queue_failed', $pending->fresh()->error_code);
        $this->assertSame('outcome_unknown', $running->fresh()->error_code);
        $this->assertSame('completed', $complete->fresh()->status);
        $this->assertTrue((new GeneratePullRequestAiAnalysisJob($complete->id))->failOnTimeout);
    }

    /** @return array{Project, RepositoryChangeRequest, User} */
    private function fixture(): array
    {
        $project = Project::factory()->create();
        $actor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $actor->id,
            'role' => WorkspaceRole::OWNER,
            'suspended_at' => null,
        ]);
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => (string) (1000 + $project->id),
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => '200',
            'full_name' => 'example/repo',
            'default_branch' => 'main',
            'start_sha' => self::SHA,
            'last_synced_sha' => self::SHA,
            'connected_at' => now(),
        ]);
        $pull = $repository->changeRequests()->create([
            'external_change_request_id' => '410',
            'number' => 10,
            'title' => 'Add reports',
            'web_url' => 'https://github.com/example/repo/pull/10',
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'head_sha' => self::SHA,
        ]);
        return [$project, $pull, $actor];
    }

    private function analysis(Project $project, RepositoryChangeRequest $pull, User $actor, string $status, int $minutesOld): PullRequestAiAnalysis
    {
        $analysis = app(PullRequestAiAnalysisHistory::class)->createPending($actor, $project, $pull);
        $when = now()->subMinutes($minutesOld);
        $analysis->forceFill([
            'status' => $status,
            'created_at' => $when,
            'started_at' => $status === 'running' ? $when : null,
            'finished_at' => $status === 'completed' ? $when : null,
        ])->save();
        return $analysis;
    }
}
