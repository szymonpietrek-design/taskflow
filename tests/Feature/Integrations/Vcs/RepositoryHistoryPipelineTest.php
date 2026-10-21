<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubCommitReader;
use App\Integrations\Vcs\BackfillGitHubLegacyData;
use App\Integrations\Vcs\RepositoryAuthorBackfiller;
use App\Integrations\Vcs\RepositorySynchronizer;
use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\VcsInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RepositoryHistoryPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HEAD = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_github_adapter_imports_into_shared_tables_without_changing_legacy_cursor(): void
    {
        [$repository, $legacy] = $this->repository();
        $this->mockReader()->shouldReceive('changesSince')->once()
            ->with(456, 'example/api', 'main', self::BASE, 123)
            ->andReturn(['head_sha' => self::HEAD, 'commits' => [$this->commit()]]);

        $result = app(RepositorySynchronizer::class)->synchronize($repository);
        $this->assertSame(['fetched' => 1, 'imported' => 1, 'head_sha' => self::HEAD], $result);
        $this->assertDatabaseHas('repository_commits', [
            'repository_connection_id' => $repository->id,
            'sha' => self::HEAD,
            'author_external_user_id' => '54321',
        ]);
        $this->assertDatabaseHas('repository_sync_runs', [
            'repository_connection_id' => $repository->id,
            'status' => 'completed',
            'fetched_count' => 1,
            'imported_count' => 1,
        ]);
        $this->assertSame(self::HEAD, $repository->fresh()->last_synced_sha);
        // A separate rollout is required before these become live writes.
        $this->assertSame(self::BASE, $legacy->fresh()->last_synced_sha);
        $this->assertDatabaseCount('github_commits', 0);
    }

    public function test_second_run_is_idempotent_and_never_duplicates_shas(): void
    {
        [$repository] = $this->repository();
        $this->mockReader()->shouldReceive('changesSince')->twice()->andReturn(
            ['head_sha' => self::HEAD, 'commits' => [$this->commit()]],
            ['head_sha' => self::HEAD, 'commits' => []],
        );
        $pipeline = app(RepositorySynchronizer::class);
        $pipeline->synchronize($repository);
        $result = $pipeline->synchronize($repository);

        $this->assertSame(0, $result['imported']);
        $this->assertDatabaseCount('repository_commits', 1);
        $this->assertDatabaseCount('repository_sync_runs', 2);
        $this->assertDatabaseHas('repository_sync_runs', ['status' => 'completed', 'imported_count' => 0]);
    }

    public function test_new_head_without_commit_batch_is_rejected(): void
    {
        [$repository] = $this->repository();
        $this->mockReader()->shouldReceive('changesSince')->once()
            ->andReturn(['head_sha' => self::HEAD, 'commits' => []]);

        $this->expectExceptionMessage('Provider returned a new HEAD without its commits.');
        try {
            app(RepositorySynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseHas('repository_connections', ['id' => $repository->id, 'last_synced_sha' => self::BASE]);
            $this->assertDatabaseCount('repository_commits', 0);
            $this->assertDatabaseHas('repository_sync_runs', ['status' => 'failed']);
        }
    }

    public function test_remote_failure_does_not_advance_cursor_and_records_failure(): void
    {
        [$repository] = $this->repository();
        $this->mockReader()->shouldReceive('changesSince')->once()
            ->andThrow(new RuntimeException('Remote GitHub API failed.'));

        try {
            app(RepositorySynchronizer::class)->synchronize($repository);
            $this->fail('The provider exception should propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Remote GitHub API failed.', $exception->getMessage());
        }
        $this->assertSame(self::BASE, $repository->fresh()->last_synced_sha);
        $this->assertDatabaseCount('repository_commits', 0);
        $this->assertDatabaseHas('repository_sync_runs', [
            'status' => 'failed', 'error_message' => 'Repository synchronization failed. Retry later.',
        ]);
    }

    public function test_changed_cursor_during_api_call_is_rejected_without_import(): void
    {
        [$repository] = $this->repository();
        $this->mockReader()->shouldReceive('changesSince')->once()
            ->andReturnUsing(function () use ($repository): array {
                $repository->forceFill(['last_synced_sha' => str_repeat('c', 40)])->save();
                return ['head_sha' => self::HEAD, 'commits' => [$this->commit()]];
            });

        $this->expectExceptionMessage('Repository cursor changed during synchronization.');
        try {
            app(RepositorySynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_commits', 0);
            $this->assertSame(str_repeat('c', 40), $repository->fresh()->last_synced_sha);
            $this->assertDatabaseHas('repository_sync_runs', ['status' => 'failed']);
        }
    }

    public function test_adapter_refuses_untrusted_instance_and_does_not_call_reader(): void
    {
        [$repository] = $this->repository();
        $instance = VcsInstance::query()->sole();
        $instance->forceFill(['base_url' => 'https://github.attacker.test'])->save();
        $this->mockReader()->shouldNotReceive('changesSince');

        $this->expectExceptionMessage('Unsupported GitHub provider instance.');
        try {
            app(RepositorySynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_commits', 0);
            $this->assertDatabaseHas('repository_sync_runs', ['status' => 'failed']);
        }
    }

    public function test_unsupported_provider_is_rejected_without_network_access(): void
    {
        [$repository] = $this->repository();
        VcsInstance::query()->sole()->forceFill(['provider' => 'gitlab'])->save();
        $this->mockReader()->shouldNotReceive('changesSince');

        $this->expectExceptionMessage('Unsupported VCS provider.');
        app(RepositorySynchronizer::class)->synchronize($repository);
    }

    public function test_old_commit_authors_are_resolved_only_for_saved_shas(): void
    {
        [$repository] = $this->repository();
        $repository->commits()->create([
            'sha' => self::HEAD, 'message' => 'Old commit', 'author_login' => 'same-looking-login',
        ]);
        $reader = $this->mockReader();
        $reader->shouldReceive('authorIdentitiesForStoredCommits')->once()
            ->with(456, 'example/api', 123, [self::HEAD])
            ->andReturn([self::HEAD => 54321]);

        $result = app(RepositoryAuthorBackfiller::class)->backfill($repository);
        $this->assertSame(['checked' => 1, 'remaining' => 0], $result);
        $this->assertDatabaseHas('repository_commits', [
            'sha' => self::HEAD, 'author_external_user_id' => '54321',
        ]);
        $this->assertNotNull($repository->commits()->firstOrFail()->author_identity_checked_at);
        $this->assertDatabaseCount('repository_sync_runs', 0);
        $this->assertSame(self::BASE, $repository->fresh()->last_synced_sha);
    }

    /** @return array{RepositoryConnection,GitHubRepository} */
    private function repository(): array
    {
        $project = Project::factory()->create();
        $installation = GitHubInstallation::query()->create([
            'workspace_id' => $project->workspace_id,
            'installation_id' => 456,
            'github_account_id' => 78,
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => now(),
        ]);
        $legacy = GitHubRepository::query()->create([
            'project_id' => $project->id,
            'github_installation_id' => $installation->id,
            'github_repository_id' => 123,
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => self::BASE,
            'last_synced_sha' => self::BASE,
            'connected_at' => now(),
        ]);
        app(BackfillGitHubLegacyData::class)->run();

        return [RepositoryConnection::query()->sole(), $legacy];
    }

    private function mockReader(): GitHubCommitReader|Mockery\MockInterface
    {
        $mock = Mockery::mock(GitHubCommitReader::class);
        $this->app->instance(GitHubCommitReader::class, $mock);
        return $mock;
    }

    /** @return array<string, mixed> */
    private function commit(): array
    {
        return [
            'sha' => self::HEAD,
            'message' => 'A new change',
            'author_name' => 'Developer',
            'author_login' => 'dev',
            'author_github_user_id' => 54321,
            'committed_at' => '2026-10-21T09:00:00Z',
        ];
    }
}
