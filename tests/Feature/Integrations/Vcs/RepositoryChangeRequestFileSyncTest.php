<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestFileReader;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryChangeRequestFileSynchronizer;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Integrations\Vcs\RepositorySyncLock;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RepositoryChangeRequestFileSyncTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_saves_revision_bound_files_without_advancing_commit_cursor(): void
    {
        $repository = $this->repository();
        $pull = $this->pull($repository, 3);
        $this->reader()->shouldReceive('filesForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::HEAD]])
            ->andReturn([3 => [$this->file('src/Foo.php')]]);
        $result = app(RepositoryChangeRequestFileSynchronizer::class)->synchronize($repository);
        $this->assertSame(['checked' => 1, 'files' => 1, 'remaining' => 0], $result);
        $this->assertSame(self::HEAD, $pull->fresh()->files_head_sha);
        $this->assertSame(self::HEAD, $repository->fresh()->last_synced_sha);
        $this->assertSame(1, $pull->files()->count());
        $this->assertSame('src/Foo.php', $pull->files()->first()->path);
    }

    public function test_force_refresh_replaces_snapshot_without_duplicates(): void
    {
        $repository = $this->repository();
        $pull = $this->pull($repository, 3);
        $this->reader()->shouldReceive('filesForStoredPullRequests')->twice()
            ->andReturn([3 => [$this->file('old.php')]], [3 => [$this->file('new.php')]]);
        $sync = app(RepositoryChangeRequestFileSynchronizer::class);
        $sync->synchronize($repository);
        $this->assertSame(0, $sync->synchronize($repository)['checked']);
        $sync->synchronize($repository, force: true);
        $this->assertSame(['new.php'], $pull->files()->pluck('path')->all());
    }

    public function test_invalid_second_file_rolls_back_the_whole_batch(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 3);
        $this->pull($repository, 4);
        $invalid = $this->file('bad.php');
        $invalid['path_hash'] = str_repeat('0', 64);
        $this->reader()->shouldReceive('filesForStoredPullRequests')->once()
            ->andReturn([3 => [$this->file('good.php')], 4 => [$invalid]]);
        $this->expectException(RuntimeException::class);
        try {
            app(RepositoryChangeRequestFileSynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_change_request_files', 0);
            $this->assertSame(2, RepositoryChangeRequest::query()->whereNull('files_synced_at')->count());
        }
    }

    public function test_changed_head_requires_a_new_snapshot(): void
    {
        $repository = $this->repository();
        $pull = $this->pull($repository, 3);
        $this->reader()->shouldReceive('filesForStoredPullRequests')->twice()
            ->andReturn([3 => [$this->file('old.php')]], [3 => [$this->file('new.php')]]);
        $sync = app(RepositoryChangeRequestFileSynchronizer::class);
        $sync->synchronize($repository);
        $pull->forceFill(['head_sha' => str_repeat('b', 40)])->save();
        $this->assertNotSame($pull->fresh()->head_sha, $pull->files_head_sha);
        $sync->synchronize($repository);
        $this->assertSame(str_repeat('b', 40), $pull->fresh()->files_head_sha);
    }

    public function test_untrusted_instance_is_rejected_before_github_reader(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 3);
        $repository->connection->instance->forceFill(['base_url' => 'https://internal.invalid'])->save();
        $this->reader()->shouldNotReceive('filesForStoredPullRequests');
        $this->expectExceptionMessage('Unsupported GitHub instance');
        app(RepositoryChangeRequestFileSynchronizer::class)->synchronize($repository);
    }

    public function test_specific_pr_only_does_not_refresh_another_pr(): void
    {
        $repository = $this->repository();
        $a = $this->pull($repository, 3);
        $b = $this->pull($repository, 4);
        $this->reader()->shouldReceive('filesForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [['number' => 4, 'head_sha' => self::HEAD]])
            ->andReturn([4 => [$this->file('new.php')]]);
        app(RepositoryChangeRequestFileSynchronizer::class)->synchronize($repository, force: true, pullNumber: 4);
        $this->assertNull($a->fresh()->files_synced_at);
        $this->assertNotNull($b->fresh()->files_synced_at);
    }

    public function test_concurrent_repository_operation_does_not_write_file_data(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 3);
        $this->reader()->shouldNotReceive('filesForStoredPullRequests');
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        $this->assertTrue($lock->get());
        try {
            $this->expectExceptionMessage('already in progress');
            app(RepositoryChangeRequestFileSynchronizer::class)->synchronize($repository);
        } finally {
            $lock->release();
            $this->assertDatabaseCount('repository_change_request_files', 0);
        }
    }

    private function reader(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GitHubPullRequestFileReader::class);
        $this->app->instance(GitHubPullRequestFileReader::class, $mock);
        return $mock;
    }

    private function repository(): RepositoryConnection
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id, 'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '10', 'account_login' => 'example', 'connected_at' => now(),
        ]);
        return RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => '20', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::HEAD,
            'last_synced_sha' => self::HEAD, 'connected_at' => now(),
        ]);
    }

    private function pull(RepositoryConnection $repository, int $number): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create([
            'external_change_request_id' => (string) (1000 + $number),
            'number' => $number, 'title' => 'PR '.$number,
            'web_url' => 'https://github.com/example/repo/pull/'.$number,
            'state' => 'open', 'head_sha' => self::HEAD,
            'remote_updated_at' => now(),
        ]);
    }

    private function file(string $path): array
    {
        return [
            'path' => $path, 'path_hash' => hash('sha256', $path), 'previous_path' => null,
            'status' => 'modified', 'additions' => 3, 'deletions' => 1, 'changes' => 4,
            'blob_sha' => self::HEAD, 'patch' => '@@ -1 +1 @@', 'patch_truncated' => false,
        ];
    }
}
