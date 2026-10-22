<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Ai\PullRequestAiAnalysisHistory;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class PullRequestAiAnalysisHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_schema_and_provider_neutral_relations_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('pull_request_ai_analyses'));
        $this->assertTrue(Schema::hasColumns('pull_request_ai_analyses', [
            'project_id', 'repository_change_request_id', 'source_key', 'source_head_sha',
            'requested_by', 'version', 'include_patch_excerpts', 'status',
            'result_markdown', 'ai_provider', 'ai_model', 'prompt_tokens', 'completion_tokens',
        ]));

        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $analysis = app(PullRequestAiAnalysisHistory::class)->createPending($admin, $project, $pull);
        $this->assertSame($analysis->id, $project->pullRequestAiAnalyses()->first()->id);
        $this->assertSame($analysis->id, $pull->aiAnalyses()->first()->id);
        $this->assertSame($admin->id, $analysis->requester->id);
    }

    public function test_pending_versions_preserve_revision_and_are_not_overwritten(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $service = app(PullRequestAiAnalysisHistory::class);
        $first = $service->createPending($admin, $project, $pull, 'pl');
        $pull->forceFill(['head_sha' => str_repeat('b', 40)])->save();
        $second = $service->createPending($admin, $project, $pull, 'pt-BR');

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(self::HEAD, $first->fresh()->source_head_sha);
        $this->assertSame(str_repeat('b', 40), $second->source_head_sha);
        $this->assertSame('pt-br', $second->language);
        $this->assertSame(PullRequestAiAnalysis::STATUS_PENDING, $second->status);
        $this->assertNull($second->result_markdown);
        $this->assertNull($second->input_digest);
        $this->assertNull($second->ai_provider);
        $this->assertSame([2, 1], $service->forPull($admin, $project, $pull)->pluck('version')->all());
        $this->assertDatabaseCount('pull_request_ai_analyses', 2);
    }

    public function test_patches_require_explicit_opt_in_and_a_current_file_snapshot(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $service = app(PullRequestAiAnalysisHistory::class);

        try {
            $service->createPending($admin, $project, $pull, includePatchExcerpts: true);
            $this->fail('Expected stale file snapshot to be refused');
        } catch (InvalidArgumentException $exception) {
            $this->assertDatabaseCount('pull_request_ai_analyses', 0);
        }

        $pull->forceFill(['files_head_sha' => self::HEAD, 'files_synced_at' => now()])->save();
        $analysis = $service->createPending($admin, $project, $pull, includePatchExcerpts: true);
        $this->assertTrue($analysis->include_patch_excerpts);
        $this->assertNull($analysis->result_markdown);
    }

    public function test_incorrect_head_and_language_are_rejected_without_writes(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $service = app(PullRequestAiAnalysisHistory::class);
        foreach (['INVALID SHA' => 'en', self::HEAD => '../en'] as $head => $language) {
            $pull->forceFill(['head_sha' => $head])->save();
            try {
                $service->createPending($admin, $project, $pull, $language);
                $this->fail('Invalid input was accepted');
            } catch (InvalidArgumentException $exception) {
                $this->assertDatabaseCount('pull_request_ai_analyses', 0);
            }
        }
    }

    public function test_only_active_workspace_administrator_can_request_analysis(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $outsider = User::factory()->create();
        $service = app(PullRequestAiAnalysisHistory::class);

        try {
            $service->createPending($outsider, $project, $pull);
            $this->fail('Outsider was authorized');
        } catch (AuthorizationException $exception) {
            $this->assertDatabaseCount('pull_request_ai_analyses', 0);
        }

        WorkspaceMember::query()->where('workspace_id', $project->workspace_id)
            ->where('user_id', $admin->id)->update(['suspended_at' => now()]);
        $this->expectException(AuthorizationException::class);
        $service->createPending($admin, $project, $pull);
    }

    public function test_archived_project_cannot_accept_new_analysis_requests(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $project->forceFill(['archived_at' => now()])->save();
        $this->expectException(AuthorizationException::class);
        app(PullRequestAiAnalysisHistory::class)->createPending($admin, $project, $pull);
    }

    public function test_cross_project_pr_is_never_accepted_or_shown(): void
    {
        [$project, , $admin] = $this->fixture();
        [, $otherRepository] = $this->fixture();
        $otherPull = $this->pull($otherRepository);
        $service = app(PullRequestAiAnalysisHistory::class);
        $this->expectException(ModelNotFoundException::class);
        $service->createPending($admin, $project, $otherPull);
    }

    public function test_repository_disconnect_does_not_destroy_analysis_history(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $service = app(PullRequestAiAnalysisHistory::class);
        $analysis = $service->createPending($admin, $project, $pull);

        $repository->delete();
        $this->assertNull($analysis->fresh()->repository_change_request_id);
        $this->assertSame(self::HEAD, $analysis->fresh()->source_head_sha);
        $this->assertSame('example/repo', $analysis->fresh()->source_repository);
        $this->assertCount(1, $service->forProject($admin, $project));
        $this->assertDatabaseCount('pull_request_ai_analyses', 1);
    }

    public function test_reconnection_to_the_same_external_pr_keeps_version_sequence(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $service = app(PullRequestAiAnalysisHistory::class);
        $service->createPending($admin, $project, $this->pull($repository));
        $connection = $repository->connection;
        $repository->delete();

        $replacement = $connection->repositories()->create([
            'project_id' => $project->id,
            'external_repository_id' => '200', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::HEAD,
            'last_synced_sha' => self::HEAD, 'connected_at' => now(),
        ]);
        $second = $service->createPending($admin, $project, $this->pull($replacement));
        $this->assertSame(2, $second->version);
        $this->assertCount(2, $service->forPull($admin, $project, $second->changeRequest));
    }

    public function test_project_history_requires_view_permission_and_has_bounded_results(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $pull = $this->pull($repository);
        $service = app(PullRequestAiAnalysisHistory::class);
        foreach (range(1, 3) as $ignored) {
            $service->createPending($admin, $project, $pull);
        }
        $this->assertCount(2, $service->forProject($admin, $project, 2));
        $this->assertCount(1, $service->forProject($admin, $project, -50));
        $this->expectException(AuthorizationException::class);
        $service->forProject(User::factory()->create(), $project);
    }

    public function test_default_serialization_does_not_expose_analysis_result_or_digest(): void
    {
        [$project, $repository, $admin] = $this->fixture();
        $record = app(PullRequestAiAnalysisHistory::class)->createPending($admin, $project, $this->pull($repository));
        $record->forceFill(['result_markdown' => 'Private generated output', 'input_digest' => str_repeat('f', 64)])->save();
        $array = $record->fresh()->toArray();
        $this->assertArrayNotHasKey('result_markdown', $array);
        $this->assertArrayNotHasKey('input_digest', $array);
    }

    /** @return array{Project, RepositoryConnection, User} */
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
        return [$project, $repository, $admin];
    }

    private function pull(RepositoryConnection $repo): RepositoryChangeRequest
    {
        return $repo->changeRequests()->create([
            'external_change_request_id' => '410', 'number' => 10,
            'title' => 'Add reports',
            'web_url' => 'https://github.com/example/repo/pull/10',
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'head_sha' => self::HEAD,
        ]);
    }
}
