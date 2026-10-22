<?php

namespace Tests\Feature\Projects;

use App\Contracts\Ai\PullRequestAnalysisGenerator;
use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use App\Services\Ai\GeneratedPullRequestAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_successful_metadata_only_submission_creates_private_version_and_html_history(): void
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
        $this->actingAs($owner)->post($url, $payload)
            ->assertRedirect(route('projects.github.pull.show', [$project, 10]));
        $this->assertSame(2, $fake->calls);
        $this->assertDatabaseCount('pull_request_ai_analyses', 2);
        $this->assertSame([1, 2], PullRequestAiAnalysis::query()->orderBy('version')->pluck('version')->all());

        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('ai.history', 2)
                ->where('ai.history.0.version', 2)
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
        $this->assertSame(1, $fake->calls);
        $this->assertSame(1, $fake->patchCalls);
        $this->assertTrue(PullRequestAiAnalysis::query()->firstOrFail()->include_patch_excerpts);
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
        ])->assertSessionHasErrors('analysis');
        $this->assertSame(1, $fake->calls);
        $this->assertSame('failed', PullRequestAiAnalysis::query()->firstOrFail()->status);
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
