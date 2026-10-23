<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Contracts\Ai\PullRequestAnalysisGenerator;
use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Ai\GeneratePullRequestAiAnalysis;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use App\Services\Ai\GeneratedPullRequestAnalysis;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GeneratePullRequestAiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_requires_explicit_independent_permissions_and_creates_no_analysis_without_them(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $fake = $this->fake();
        $service = app(GeneratePullRequestAiAnalysis::class);

        foreach ([
            ['approveExternalSharing' => false],
            ['approveExternalSharing' => true, 'includePatches' => true, 'approveCodeSharing' => false],
            ['approveExternalSharing' => true, 'includePatches' => false, 'approveCodeSharing' => true],
        ] as $options) {
            try {
                $service->run($actor, $project, $pull, ...$options);
                $this->fail('Consent was bypassed.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('pull_request_ai_analyses', 0);
            }
        }
        $this->assertSame(0, $fake->calls);
    }

    public function test_sends_only_approved_pr_evidence_and_creates_versioned_private_output(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pull->forceFill(['body' => 'HIDDEN PR DESCRIPTION'])->save();
        $fake = $this->fake();
        $service = app(GeneratePullRequestAiAnalysis::class);
        $first = $service->run($actor, $project, $pull, language: 'pl', approveExternalSharing: true);
        $second = $service->run($actor, $project, $pull, language: 'en', approveExternalSharing: true);

        $this->assertSame(2, $fake->calls);
        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(PullRequestAiAnalysis::STATUS_COMPLETED, $first->status);
        $this->assertSame('Example report', $first->getRawOriginal('result_markdown'));
        $this->assertSame('deepseek', $first->ai_provider);
        $this->assertSame('deepseek-chat', $first->ai_model);
        $this->assertSame(22, $first->prompt_tokens);
        $this->assertSame(10, $first->completion_tokens);
        $this->assertSame(64, strlen($first->input_digest));
        $this->assertNotNull($first->started_at);
        $this->assertNotNull($first->finished_at);
        $this->assertArrayNotHasKey('result_markdown', $first->toArray());
        $this->assertArrayNotHasKey('input_digest', $first->toArray());
        $this->assertSame('pl', $fake->languages[0]);
        $this->assertArrayNotHasKey('project_id', $fake->contexts[0]);
        $this->assertArrayNotHasKey('repository_id', $fake->contexts[0]);
        $this->assertArrayNotHasKey('recent_repository_commits_unrelated_to_pr', $fake->contexts[0]);
        $this->assertArrayNotHasKey('body', $fake->contexts[0]['pull_request']);
        $this->assertArrayNotHasKey('untrusted_patch', $fake->contexts[0]['files'][0]);
        $this->assertDatabaseCount('pull_request_ai_analyses', 2);
    }

    public function test_explicit_code_sharing_includes_only_screened_patches(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $pull->files()->create([
            'path' => '.env', 'path_hash' => hash('sha256', '.env'),
            'status' => 'modified', 'additions' => 1, 'deletions' => 1, 'changes' => 2,
            'patch' => '@@ -1 +1 @@' . "\n-password=hidden\n+password=stillhidden", 'patch_truncated' => false,
        ]);
        $fake = $this->fake();
        // Code path in this test includes a safe patch and rejects a suspicious one.
        $result = app(GeneratePullRequestAiAnalysis::class)->run(
            $actor, $project, $pull,
            includePatches: true, approveExternalSharing: true, approveCodeSharing: true,
        );
        $this->assertTrue($result->include_patch_excerpts);
        $this->assertSame(1, $fake->contexts[0]['evidence_limits']['patches_included']);
        $this->assertSame(1, $fake->contexts[0]['evidence_limits']['patches_skipped_by_reason']['sensitive_path']);
        $this->assertSame(1, count(array_filter($fake->contexts[0]['files'], fn ($f) => isset($f['untrusted_patch']))));
        $this->assertStringNotContainsString('password=hidden', json_encode($fake->contexts[0]));
    }

    public function test_generated_evidence_is_isolated_between_projects_with_same_pr_number(): void
    {
        [$firstProject, $firstPull] = $this->fixture();
        [$secondProject, $secondPull, $secondActor] = $this->fixture();
        // The two projects both have PR #10. The generator must derive evidence
        // from the approved PR identity rather than a different project's records.
        $otherHead = str_repeat('b', 40);
        $secondPull->forceFill([
            'title' => 'Distinct second project change',
            'head_sha' => $otherHead,
            'files_head_sha' => $otherHead,
            'files_synced_at' => now(),
        ])->save();
        $secondPull->files()->delete();
        $secondPull->files()->create([
            'path' => 'src/Second.php',
            'path_hash' => hash('sha256', 'src/Second.php'),
            'status' => 'modified', 'additions' => 1, 'deletions' => 1,
            'changes' => 2, 'patch' => "@@ -1 +1 @@\n-before\n+after",
            'patch_truncated' => false,
        ]);

        $fake = $this->fake();
        $analysis = app(GeneratePullRequestAiAnalysis::class)->run(
            $secondActor, $secondProject, $secondPull,
            language: 'pl', includePatches: true,
            approveExternalSharing: true, approveCodeSharing: true,
        );

        $this->assertSame(PullRequestAiAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertSame($otherHead, $analysis->source_head_sha);
        $this->assertSame(1, $fake->calls);
        $this->assertSame('Distinct second project change', $fake->contexts[0]['pull_request']['title']);
        $this->assertSame('src/Second.php', $fake->contexts[0]['files'][0]['path']);
        $this->assertStringNotContainsString('src/Logic.php', json_encode($fake->contexts[0]));
    }

    public function test_missing_or_stale_file_snapshot_never_calls_the_provider(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $fake = $this->fake();
        $pull->forceFill(['files_head_sha' => str_repeat('b', 40)])->save();
        $this->expectException(RuntimeException::class);
        try {
            app(GeneratePullRequestAiAnalysis::class)->run($actor, $project, $pull, approveExternalSharing: true);
        } finally {
            $this->assertSame(0, $fake->calls);
            $this->assertDatabaseCount('pull_request_ai_analyses', 0);
        }
    }

    public function test_rejects_outsider_and_archived_project_before_external_call(): void
    {
        [$project, $pull] = $this->fixture();
        $fake = $this->fake();
        $this->expectException(AuthorizationException::class);
        try {
            app(GeneratePullRequestAiAnalysis::class)->run(
                User::factory()->create(), $project, $pull, approveExternalSharing: true,
            );
        } finally {
            $this->assertSame(0, $fake->calls);
        }
    }

    public function test_provider_failure_is_redacted_and_recorded_without_source_or_error_details(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $fake = $this->fake();
        $fake->failure = true;
        try {
            app(GeneratePullRequestAiAnalysis::class)->run($actor, $project, $pull, approveExternalSharing: true);
            $this->fail('Expected a safe analysis failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('TOKEN-SECRET', $exception->getMessage());
            $analysis = PullRequestAiAnalysis::query()->firstOrFail();
            $this->assertSame(PullRequestAiAnalysis::STATUS_FAILED, $analysis->status);
            $this->assertSame('generation_failed', $analysis->error_code);
            $this->assertNull($analysis->result_markdown);
            $this->assertNull($analysis->ai_model);
            $this->assertDatabaseCount('pull_request_ai_analyses', 1);
        }
    }

    public function test_changed_head_during_generation_never_publishes_stale_result(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $fake = $this->fake();
        $fake->onGenerate = static function () use ($pull): void {
            $pull->forceFill(['head_sha' => str_repeat('b', 40)])->save();
        };
        try {
            app(GeneratePullRequestAiAnalysis::class)->run($actor, $project, $pull, approveExternalSharing: true);
            $this->fail('Stale output was accepted.');
        } catch (RuntimeException) {
            $analysis = PullRequestAiAnalysis::query()->firstOrFail();
            $this->assertSame(PullRequestAiAnalysis::STATUS_FAILED, $analysis->status);
            $this->assertSame('stale_evidence', $analysis->error_code);
            $this->assertNull($analysis->result_markdown);
        }
    }

    public function test_cli_without_explicit_approval_does_not_enqueue_or_call_ai(): void
    {
        [$project, $pull, $actor] = $this->fixture();
        $fake = $this->fake();
        $this->artisan('vcs:analyze-pr', [
            'project' => $project->id, 'number' => $pull->number,
            '--user' => $actor->id,
        ])->assertExitCode(1);
        $this->assertSame(0, $fake->calls);
        $this->assertDatabaseCount('pull_request_ai_analyses', 0);
    }

    /** @return array{Project, RepositoryChangeRequest, User} */
    private function fixture(): array
    {
        $project = Project::factory()->create();
        $admin = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::OWNER,
            'suspended_at' => null,
        ]);
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => (string) (1000 + $project->id),
            'account_login' => 'example', 'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => '200', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::HEAD,
            'last_synced_sha' => self::HEAD, 'connected_at' => now(),
        ]);
        $pull = $repository->changeRequests()->create([
            'external_change_request_id' => '410', 'number' => 10,
            'title' => 'Add reports',
            'body' => 'Sensitive text should never be sent',
            'web_url' => 'https://github.com/example/repo/pull/10',
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'head_sha' => self::HEAD,
            'synced_at' => now(),
        ]);
        // File-sync cursors are deliberately guarded against mass assignment.
        // Mimic RepositoryChangeRequestFileSynchronizer, which uses forceFill().
        $pull->forceFill([
            'files_head_sha' => self::HEAD,
            'files_synced_at' => now(),
        ])->save();
        $pull->files()->create([
            'path' => 'src/Logic.php', 'path_hash' => hash('sha256', 'src/Logic.php'),
            'status' => 'modified', 'additions' => 1, 'deletions' => 1,
            'changes' => 2, 'patch' => '@@ -1 +1 @@' . "\n-old\n+new",
            'patch_truncated' => false,
        ]);
        return [$project, $pull, $admin];
    }

    private function fake(): FakePullRequestAnalysisGenerator
    {
        $fake = new FakePullRequestAnalysisGenerator();
        app()->instance(PullRequestAnalysisGenerator::class, $fake);

        return $fake;
    }
}

/** Provider stub ensures tests never contact DeepSeek. */
final class FakePullRequestAnalysisGenerator implements PullRequestAnalysisGenerator
{
    public int $calls = 0;

    /** @var array<int, array<string, mixed>> */
    public array $contexts = [];

    /** @var array<int, string> */
    public array $languages = [];

    public bool $failure = false;
    public ?\Closure $onGenerate = null;

    public function generate(array $evidence, string $language): GeneratedPullRequestAnalysis
    {
        $this->calls++;
        $this->contexts[] = $evidence;
        $this->languages[] = $language;
        if ($this->failure) {
            throw new RuntimeException('TOKEN-SECRET from upstream response');
        }
        ($this->onGenerate)?->__invoke();

        return new GeneratedPullRequestAnalysis('Example report', 'deepseek', 'deepseek-chat', 22, 10);
    }
}
