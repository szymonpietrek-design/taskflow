<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestReader;
use App\Integrations\Vcs\RepositoryChangeRequestSynchronizer;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use App\Models\VcsInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RepositoryChangeRequestSyncTest extends TestCase
{
    use RefreshDatabase;

    private const CURSOR = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_imports_pull_requests_without_touching_commit_cursor(): void
    {
        $repository = $this->repository();
        $this->reader()->shouldReceive('listPullRequests')->once()->andReturn([
            $this->githubPr(11, 2), $this->githubPr(12, 3, ['state' => 'closed', 'merged_at' => '2026-10-21T10:00:00Z']),
        ]);

        $result = app(RepositoryChangeRequestSynchronizer::class)->synchronize($repository);

        $this->assertSame(['fetched' => 2, 'created' => 2, 'updated' => 0], $result);
        $this->assertDatabaseHas('repository_change_requests', [
            'repository_connection_id' => $repository->id,
            'external_change_request_id' => '12',
            'number' => 3,
            'state' => 'merged',
            'author_external_user_id' => '45',
        ]);
        $this->assertSame(self::CURSOR, $repository->fresh()->last_synced_sha);
        $this->assertDatabaseCount('repository_commits', 0);
    }

    public function test_repeat_sync_is_idempotent_and_does_not_count_unchanged_rows_as_updates(): void
    {
        $repository = $this->repository();
        $reader = $this->reader();
        $reader->shouldReceive('listPullRequests')->twice()->andReturn([$this->githubPr(11, 2)]);

        $service = app(RepositoryChangeRequestSynchronizer::class);
        $this->assertSame(1, $service->synchronize($repository)['created']);
        $this->assertSame(['fetched' => 1, 'created' => 0, 'updated' => 0], $service->synchronize($repository));
        $this->assertDatabaseCount('repository_change_requests', 1);
    }

    public function test_updates_state_and_title_without_duplicate_and_preserves_remote_time(): void
    {
        $repository = $this->repository();
        $reader = $this->reader();
        $reader->shouldReceive('listPullRequests')->twice()->andReturn(
            [$this->githubPr(11, 2)],
            [$this->githubPr(11, 2, [
                'title' => 'Now merged',
                'state' => 'closed',
                'merged_at' => '2026-10-22T09:00:00Z',
                'updated_at' => '2026-10-22T10:00:00Z',
            ])],
        );

        $service = app(RepositoryChangeRequestSynchronizer::class);
        $service->synchronize($repository);
        $this->assertSame(1, $service->synchronize($repository)['updated']);
        $this->assertDatabaseHas('repository_change_requests', [
            'repository_connection_id' => $repository->id,
            'number' => 2,
            'title' => 'Now merged',
            'state' => 'merged',
            'merged_at' => '2026-10-22 09:00:00',
        ]);
        $this->assertDatabaseCount('repository_change_requests', 1);
    }

    public function test_same_external_id_in_different_repository_is_not_collision(): void
    {
        $first = $this->repository('10', '20');
        $second = $this->repository('11', '21');
        $reader = $this->reader();
        $reader->shouldReceive('listPullRequests')->twice()->andReturn([$this->githubPr(11, 2)]);
        app(RepositoryChangeRequestSynchronizer::class)->synchronize($first);
        app(RepositoryChangeRequestSynchronizer::class)->synchronize($second);
        $this->assertDatabaseCount('repository_change_requests', 2);
    }

    public function test_invalid_pr_in_batch_does_not_partially_persist(): void
    {
        $repository = $this->repository();
        $reader = $this->reader();
        $reader->shouldReceive('listPullRequests')->once()->andReturn([
            $this->githubPr(11, 2), $this->githubPr(12, 3, ['head' => ['sha' => 'BAD', 'ref' => 'topic']]),
        ]);
        $this->expectException(RuntimeException::class);
        try {
            app(RepositoryChangeRequestSynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_change_requests', 0);
        }
    }

    public function test_mismatched_github_instance_fails_before_calling_api(): void
    {
        $repository = $this->repository();
        $repository->connection->instance->forceFill(['base_url' => 'https://example.test'])->save();
        $this->reader()->shouldNotReceive('listPullRequests');
        $this->expectExceptionMessage('Unsupported GitHub instance');
        app(RepositoryChangeRequestSynchronizer::class)->synchronize($repository);
    }

    public function test_api_failure_does_not_change_saved_snapshots(): void
    {
        $repository = $this->repository();
        $reader = $this->reader();
        $reader->shouldReceive('listPullRequests')->twice()
            ->andReturnUsing(function (): array {
                static $calls = 0;
                if (++$calls === 1) {
                    return [$this->githubPr(11, 2)];
                }
                throw new RuntimeException('External GitHub failure');
            });
        $service = app(RepositoryChangeRequestSynchronizer::class);
        $service->synchronize($repository);
        try {
            $service->synchronize($repository);
            $this->fail('Expected GitHub failure');
        } catch (RuntimeException $e) {
            $this->assertDatabaseCount('repository_change_requests', 1);
            $this->assertSame(self::CURSOR, $repository->fresh()->last_synced_sha);
        }
    }

    public function test_conflicting_public_pr_number_rolls_back(): void
    {
        $repository = $this->repository();
        $this->reader()->shouldReceive('listPullRequests')->once()->andReturn([
            $this->githubPr(11, 2), $this->githubPr(12, 2),
        ]);
        $this->expectException(RuntimeException::class);
        try {
            app(RepositoryChangeRequestSynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_change_requests', 0);
        }
    }

    private function reader(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(GitHubPullRequestReader::class);
        $this->app->instance(GitHubPullRequestReader::class, $mock);
        return $mock;
    }

    private function repository(string $installationId = '10', string $repositoryId = '20'): RepositoryConnection
    {
        $project = Project::factory()->create();
        $instance = VcsInstance::query()->firstOrCreate(
            ['provider' => 'github', 'instance_key' => hash('sha256', 'https://github.com')],
            ['base_url' => 'https://github.com'],
        );
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => $instance->id,
            'external_connection_id' => $installationId,
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        return RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => $repositoryId,
            'full_name' => 'example/repo',
            'default_branch' => 'main',
            'start_sha' => self::CURSOR,
            'last_synced_sha' => self::CURSOR,
            'connected_at' => now(),
        ]);
    }

    private function githubPr(int $id, int $number, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'number' => $number,
            'title' => 'My PR',
            'body' => 'Description',
            'state' => 'open',
            'draft' => false,
            'locked' => false,
            'created_at' => '2026-10-20T10:00:00Z',
            'updated_at' => '2026-10-21T09:00:00Z',
            'closed_at' => null,
            'merged_at' => null,
            'merge_commit_sha' => null,
            'head' => ['sha' => str_repeat('b', 40), 'ref' => 'topic', 'repo' => ['full_name' => 'example/repo']],
            'base' => ['sha' => str_repeat('c', 40), 'ref' => 'main'],
            'user' => ['id' => 45, 'login' => 'example-user'],
        ], $overrides);
    }
}
