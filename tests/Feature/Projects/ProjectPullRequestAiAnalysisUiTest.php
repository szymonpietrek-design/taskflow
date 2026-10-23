<?php

namespace Tests\Feature\Projects;

use App\Contracts\Ai\PullRequestAnalysisGenerator;
use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Jobs\GeneratePullRequestAiAnalysisJob;
use App\Integrations\Vcs\Ai\GeneratePullRequestAiAnalysis;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use App\Services\Ai\GeneratedPullRequestAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ProjectPullRequestAiAnalysisUiTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
    }

    public function test_admin_sees_ready_state_but_consent_is_not_preselected(): void
    {
        [$project, , $owner] = $this->fixture();

        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('projects/integrations/GitHubPullRequest')
                ->where('ai.can_generate', true)
                ->where('ai.ready', true)
                ->has('ai.history', 0)
                ->etc());
        $this->assertDatabaseCount('pull_request_ai_analyses', 0);
    }

    public function test_explicit_metadata_consent_is_required_before_any_provider_call(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $url = route('projects.github.pull.ai.analyze', [$project, 10]);

        $this->actingAs($owner)->post($url, [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => false, 'approve_code_sharing' => false,
        ])->assertSessionHasErrors('approve_external_sharing');

        $this->actingAs($owner)->post($url, [
            'language' => 'pl', 'include_patches' => true,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertSessionHasErrors('approve_code_sharing');

        $this->assertSame(0, $fake->calls);
        $this->assertDatabaseCount('pull_request_ai_analyses', 0);
    }

    public function test_approved_submission_queues_only_an_id_and_worker_saves_versioned_history(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $url = route('projects.github.pull.ai.analyze', [$project, 10]);
        $payload = [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ];
        $this->actingAs($owner)->post($url, $payload)
            ->assertRedirect(route('projects.github.pull.show', [$project, 10]));

        $this->assertSame(0, $fake->calls); // Never call DeepSeek during HTTP.
        Queue::assertPushed(GeneratePullRequestAiAnalysisJob::class, 1);
        $queued = PullRequestAiAnalysis::query()->firstOrFail();
        $this->assertSame('pending', $queued->status);

        // Same PR cannot be double-queued while pending (potential double charge).
        $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('analysis');
        Queue::assertPushed(GeneratePullRequestAiAnalysisJob::class, 1);
        $this->assertDatabaseCount('pull_request_ai_analyses', 1);

        // Explicitly simulate the database worker consuming that ID-only job.
        (new GeneratePullRequestAiAnalysisJob((int) $queued->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame(1, $fake->calls);
        $this->assertSame('completed', $queued->fresh()->status);

        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('ai.history', 1)
                ->where('ai.history.0.version', 1)
                ->where('ai.history.0.status', 'completed')
                ->where('ai.history.0.include_patch_excerpts', false)
                ->where('ai.history.0.result_html', fn ($html) => str_contains($html, '<strong>')
                    && ! str_contains($html, '<script') && ! str_contains($html, 'TOKEN_SECRET'))
                ->missing('ai.history.0.input_digest')
                ->missing('ai.history.0.result_markdown')
                ->etc());
    }

    public function test_patches_need_two_consents_and_only_approved_scope_is_sent(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'en', 'include_patches' => true,
            'approve_external_sharing' => true, 'approve_code_sharing' => true,
        ])->assertRedirect();
        $this->assertSame(0, $fake->calls);
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        $this->assertTrue($analysis->include_patch_excerpts);
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame(1, $fake->calls);
        $this->assertSame(1, $fake->patchCalls);
    }

    public function test_outsiders_and_archived_projects_cannot_start_analysis(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $outsider = User::factory()->create();
        $url = route('projects.github.pull.ai.analyze', [$project, 10]);
        $data = [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ];
        $this->actingAs($outsider)->get(route('projects.github.pull.show', [$project, 10]))->assertForbidden();
        $this->actingAs($outsider)->post($url, $data)->assertForbidden();
        $project->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->post($url, $data)->assertForbidden();
        $this->assertSame(0, $fake->calls);
    }

    public function test_cross_project_pr_cannot_be_analyzed(): void
    {
        [$project, , $owner] = $this->fixture();
        [$otherProject] = $this->fixture();
        $fake = $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$otherProject, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertForbidden();
        $this->assertSame(0, $fake->calls);
    }

    public function test_failure_is_redacted_and_history_does_not_expose_provider_exception(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $fake->fail = true;
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertRedirect();
        $this->assertSame(0, $fake->calls);
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame(1, $fake->calls);
        $this->assertSame('failed', $analysis->fresh()->status);
        $response = $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]));
        $response->assertOk()->assertDontSee('TOKEN_SECRET', false)->assertInertia(fn (Assert $page) => $page
            ->where('ai.history.0.result_html', '')
            ->missing('ai.history.0.error_code')
            ->etc());
    }

    public function test_stale_snapshots_block_generation_without_a_provider_call(): void
    {
        [$project, $pull, $owner] = $this->fixture();
        $fake = $this->fake();
        $pull->forceFill(['files_head_sha' => str_repeat('b', 40)])->save();
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page->where('ai.ready', false)->etc());
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertSessionHasErrors('analysis');
        $this->assertSame(0, $fake->calls);
        Queue::assertNotPushed(GeneratePullRequestAiAnalysisJob::class);
    }

    public function test_queued_job_rechecks_source_before_external_api_call(): void
    {
        [$project, $pull, $owner] = $this->fixture();
        $fake = $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertRedirect();
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        $pull->forceFill(['head_sha' => str_repeat('b', 40)])->save();
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame(0, $fake->calls);
        $this->assertSame('failed', $analysis->fresh()->status);
    }

    public function test_queued_job_rechecks_permission_and_cannot_run_for_archived_project(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'en', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertRedirect();
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        $project->forceFill(['archived_at' => now()])->save();
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame(0, $fake->calls);
        $this->assertSame('failed', $analysis->fresh()->status);
    }

    public function test_duplicate_delivery_does_not_regenerate_completed_analysis(): void
    {
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertRedirect();
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        $job = new GeneratePullRequestAiAnalysisJob((int) $analysis->id);
        $job->handle(app(GeneratePullRequestAiAnalysis::class));
        $job->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame(1, $fake->calls);
        $this->assertSame('completed', $analysis->fresh()->status);
        $this->assertDatabaseCount('pull_request_ai_analyses', 1);
    }

    public function test_project_quota_blocks_paid_request_before_creating_a_job(): void
    {
        config()->set('ai_pr.limits.per_project_1h', 1);
        config()->set('ai_pr.limits.per_project_24h', 2);
        [$project, , $owner] = $this->fixture();
        $payload = [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ];
        $url = route('projects.github.pull.ai.analyze', [$project, 10]);
        $this->actingAs($owner)->post($url, $payload)->assertRedirect();
        $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('analysis');
        Queue::assertPushed(GeneratePullRequestAiAnalysisJob::class, 1);
        $this->assertDatabaseCount('pull_request_ai_analyses', 1);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ai.quota.allowed', false)
                ->where('ai.quota.used_1h', 1)
                ->where('ai.quota.limit_1h', 1)
                ->etc());
        [$otherProject, , $otherOwner] = $this->fixture();
        $this->actingAs($otherOwner)->get(route('projects.github.pull.show', [$otherProject, 10]))
            ->assertInertia(fn (Assert $page) => $page->where('ai.quota.allowed', true)->etc());
    }

    public function test_failed_analysis_still_counts_toward_24_hour_budget(): void
    {
        config()->set('ai_pr.limits.per_project_1h', 0);
        config()->set('ai_pr.limits.per_project_24h', 1);
        [$project, , $owner] = $this->fixture();
        $fake = $this->fake();
        $fake->fail = true;
        $url = route('projects.github.pull.ai.analyze', [$project, 10]);
        $payload = [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ];
        $this->actingAs($owner)->post($url, $payload)->assertRedirect();
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame('failed', $analysis->fresh()->status);
        $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('analysis');
        Queue::assertPushed(GeneratePullRequestAiAnalysisJob::class, 1);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ai.quota.used_24h', 1)
                ->where('ai.quota.allowed', false)
                ->where('ai.usage_30d.failed', 1)
                ->etc());
    }

    public function test_cost_estimate_is_snapshotted_and_project_usage_is_visible(): void
    {
        config()->set('ai_pr.prices.deepseek.deepseek-chat', [
            'input_per_million_usd' => '0.300000',
            'output_per_million_usd' => '1.000000',
        ]);
        [$project, , $owner] = $this->fixture();
        $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertRedirect();
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertSame('0.00000960', $analysis->fresh()->estimated_cost_usd);
        $this->assertSame('0.300000', $analysis->fresh()->input_price_per_million_usd);
        $this->assertSame('1.000000', $analysis->fresh()->output_price_per_million_usd);
        config()->set('ai_pr.prices.deepseek.deepseek-chat.input_per_million_usd', '4.000000');
        $this->assertSame('0.00000960', $analysis->fresh()->estimated_cost_usd);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ai.usage_30d.completed', 1)
                ->where('ai.usage_30d.prompt_tokens', 12)
                ->where('ai.usage_30d.completion_tokens', 6)
                ->where('ai.usage_30d.unknown_cost', 0)
                ->where('ai.history.0.estimated_cost_usd', '0.00000960')
                ->etc());
    }

    public function test_unpriced_analysis_keeps_cost_unknown_not_zero(): void
    {
        config()->set('ai_pr.prices.deepseek.deepseek-chat', [
            'input_per_million_usd' => null, 'output_per_million_usd' => null,
        ]);
        [$project, , $owner] = $this->fixture();
        $this->fake();
        $this->actingAs($owner)->post(route('projects.github.pull.ai.analyze', [$project, 10]), [
            'language' => 'pl', 'include_patches' => false,
            'approve_external_sharing' => true, 'approve_code_sharing' => false,
        ])->assertRedirect();
        $analysis = PullRequestAiAnalysis::query()->firstOrFail();
        (new GeneratePullRequestAiAnalysisJob((int) $analysis->id))
            ->handle(app(GeneratePullRequestAiAnalysis::class));
        $this->assertNull($analysis->fresh()->estimated_cost_usd);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ai.usage_30d.unknown_cost', 1)
                ->where('ai.usage_30d.estimated_cost_usd', null)
                ->etc());
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
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => '200', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::SHA,
            'last_synced_sha' => self::SHA, 'connected_at' => now(),
        ]);
        $pull = $repository->changeRequests()->create([
            'external_change_request_id' => '410', 'number' => 10,
            'title' => 'Add reports', 'web_url' => 'https://github.com/example/repo/pull/10',
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'head_sha' => self::SHA, 'synced_at' => now(),
        ]);
        $pull->forceFill(['files_head_sha' => self::SHA, 'files_synced_at' => now()])->save();
        $pull->files()->create([
            'path' => 'src/Example.php', 'path_hash' => hash('sha256', 'src/Example.php'),
            'status' => 'modified', 'additions' => 1, 'deletions' => 1,
            'changes' => 2, 'patch' => "@@ -1 +1 @@\n-old\n+new", 'patch_truncated' => false,
        ]);
        return [$project, $pull, $admin];
    }

    private function fake(): UiFakePullRequestAnalysisGenerator
    {
        $fake = new UiFakePullRequestAnalysisGenerator();
        $this->app->instance(PullRequestAnalysisGenerator::class, $fake);
        return $fake;
    }
}

final class UiFakePullRequestAnalysisGenerator implements PullRequestAnalysisGenerator
{
    public int $calls = 0;
    public int $patchCalls = 0;
    public bool $fail = false;

    public function generate(array $evidence, string $language): GeneratedPullRequestAnalysis
    {
        $this->calls++;
        if ($this->fail) {
            throw new RuntimeException('Provider failure TOKEN_SECRET');
        }
        if ((int) ($evidence['evidence_limits']['patches_included'] ?? 0) > 0) {
            $this->patchCalls++;
        }
        return new GeneratedPullRequestAnalysis(
            markdown: '**Safe AI result**', provider: 'deepseek', model: 'deepseek-chat',
            promptTokens: 12, completionTokens: 6,
        );
    }
}
