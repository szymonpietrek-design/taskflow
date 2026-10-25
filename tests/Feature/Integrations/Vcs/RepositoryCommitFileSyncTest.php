<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubCommitFileReader;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryCommitFileSynchronizer;
use App\Integrations\Vcs\RepositorySyncLock;
use App\Models\Project;
use App\Models\RepositoryCommit;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use App\Support\Vcs\VcsSummaryEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class RepositoryCommitFileSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SHA_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const SHA_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_saves_complete_immutable_snapshots_without_advancing_commit_cursor(): void
    {
        $repository = $this->repository();
        $commit = $this->commit($repository, self::SHA_A);
        $this->reader()->shouldReceive('filesForStoredCommits')->once()
            ->with(10, 'example/repo', 20, [self::SHA_A])
            ->andReturn([self::SHA_A => [$this->file('app/Services/Features.php')]]);

        $sync = app(RepositoryCommitFileSynchronizer::class);
        $this->assertSame(['checked' => 1, 'files' => 1, 'remaining' => 0], $sync->synchronize($repository));
        $this->assertSame(0, $sync->synchronize($repository)['checked']);
        $this->assertSame(self::SHA_A, $repository->fresh()->last_synced_sha);
        $this->assertSame(1, $commit->fresh()->files_count);
        $this->assertNotNull($commit->fresh()->files_synced_at);
        $this->assertSame('app/Services/Features.php', $commit->files()->first()->path);
    }

    public function test_partial_batch_is_rejected_without_saving_files_or_snapshot_flags(): void
    {
        $repository = $this->repository();
        $a = $this->commit($repository, self::SHA_A);
        $b = $this->commit($repository, self::SHA_B);
        $this->reader()->shouldReceive('filesForStoredCommits')->once()
            ->andReturn([self::SHA_A => [$this->file('src/A.php')]]);
        $this->expectExceptionMessage('Incomplete commit file snapshots');
        try {
            app(RepositoryCommitFileSynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertNull($a->fresh()->files_synced_at);
            $this->assertNull($b->fresh()->files_synced_at);
            $this->assertDatabaseCount('repository_commit_files', 0);
        }
    }

    public function test_invalid_second_commit_file_does_not_save_first_commit(): void
    {
        $repository = $this->repository();
        $this->commit($repository, self::SHA_A);
        $this->commit($repository, self::SHA_B);
        $invalid = $this->file('invalid.php');
        $invalid['path_hash'] = str_repeat('0', 64);
        $this->reader()->shouldReceive('filesForStoredCommits')->once()
            ->andReturn([self::SHA_A => [$this->file('valid.php')], self::SHA_B => [$invalid]]);
        $this->expectExceptionMessage('Invalid normalized commit file metadata');
        try {
            app(RepositoryCommitFileSynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_commit_files', 0);
            $this->assertSame(2, RepositoryCommit::query()->whereNull('files_synced_at')->count());
        }
    }

    public function test_untrusted_provider_instance_is_rejected_before_network(): void
    {
        $repository = $this->repository();
        $this->commit($repository, self::SHA_A);
        $repository->connection->instance->forceFill(['base_url' => 'https://fake.invalid'])->save();
        $this->reader()->shouldNotReceive('filesForStoredCommits');
        $this->expectExceptionMessage('Unsupported GitHub provider instance');
        app(RepositoryCommitFileSynchronizer::class)->synchronize($repository);
    }

    public function test_shared_repository_lock_prevents_conflicting_writes(): void
    {
        $repository = $this->repository();
        $this->commit($repository, self::SHA_A);
        $this->reader()->shouldNotReceive('filesForStoredCommits');
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        $this->assertTrue($lock->get());
        try {
            $this->expectExceptionMessage('already in progress');
            app(RepositoryCommitFileSynchronizer::class)->synchronize($repository);
        } finally {
            $lock->release();
        }
    }

    public function test_report_evidence_contains_only_bounded_commit_files_in_period(): void
    {
        $repository = $this->repository();
        $inPeriod = $this->commit($repository, self::SHA_A);
        $outOfPeriod = $this->commit($repository, self::SHA_B, '2026-09-01 12:00:00');
        $inPeriod->forceFill(['files_count' => 12, 'files_synced_at' => now()])->save();
        $outOfPeriod->forceFill(['files_count' => 1, 'files_synced_at' => now()])->save();
        for ($i = 1; $i <= 12; $i++) {
            $inPeriod->files()->create($this->file('app/Service'.$i.'.php'));
        }
        $outOfPeriod->files()->create($this->file('private/OUT_OF_PERIOD.php'));

        $vcs = app(VcsSummaryEvidence::class)->forPeriod($repository->project_id, '2026-10-01', '2026-10-25');
        $this->assertCount(1, $vcs['commits']);
        $this->assertSame('complete', $vcs['commits'][0]['files_snapshot']);
        $this->assertSame(12, $vcs['commits'][0]['file_count']);
        $this->assertSame(8, $vcs['commits'][0]['files_included']);
        $this->assertStringNotContainsString('OUT_OF_PERIOD', json_encode($vcs));
        $this->assertArrayNotHasKey('patch', $vcs['commits'][0]['files'][0]);
    }

    public function test_file_budget_is_shared_across_all_commits_not_only_the_earliest(): void
    {
        $repository = $this->repository();
        foreach (range(1, 30) as $i) {
            $sha = str_pad(dechex($i), 40, '0', STR_PAD_LEFT);
            $commit = $this->commit($repository, $sha);
            $commit->forceFill(['files_count' => 5, 'files_synced_at' => now()])->save();
            foreach (range(1, 5) as $j) {
                $commit->files()->create($this->file('src/'.sprintf('%02d', $i).'/file'.$j.'.php'));
            }
        }
        $vcs = app(VcsSummaryEvidence::class)->forPeriod($repository->project_id, '2026-10-01', '2026-10-25');
        $this->assertCount(30, $vcs['commits']);
        $this->assertSame(60, array_sum(array_column($vcs['commits'], 'files_included')));
        $this->assertSame(2, $vcs['commits'][0]['files_included']);
        $this->assertSame(2, $vcs['commits'][29]['files_included']);
    }

    public function test_unsynchronized_commit_is_distinguished_from_commit_with_no_changed_files(): void
    {
        $repository = $this->repository();
        $commit = $this->commit($repository, self::SHA_A);
        $first = app(VcsSummaryEvidence::class)->forPeriod($repository->project_id, '2026-10-01', '2026-10-25');
        $this->assertSame('unavailable', $first['commits'][0]['files_snapshot']);
        $commit->forceFill(['files_synced_at' => now(), 'files_count' => 0])->save();
        $second = app(VcsSummaryEvidence::class)->forPeriod($repository->project_id, '2026-10-01', '2026-10-25');
        $this->assertSame('complete', $second['commits'][0]['files_snapshot']);
        $this->assertSame(0, $second['commits'][0]['file_count']);
    }

    private function reader(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GitHubCommitFileReader::class);
        $this->app->instance(GitHubCommitFileReader::class, $mock);
        return $mock;
    }

    private function repository(): RepositoryConnection
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '10', 'account_login' => 'example', 'connected_at' => now(),
        ]);
        return RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => '20', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::SHA_A,
            'last_synced_sha' => self::SHA_A, 'connected_at' => now(),
        ]);
    }

    private function commit(RepositoryConnection $repository, string $sha, string $date = '2026-10-12 12:00:00'): RepositoryCommit
    {
        return RepositoryCommit::query()->create([
            'repository_connection_id' => $repository->id,
            'sha' => $sha, 'message' => 'feat: improve reports', 'committed_at' => $date,
        ]);
    }

    private function file(string $path): array
    {
        return [
            'path' => $path, 'path_hash' => hash('sha256', $path), 'previous_path' => null,
            'status' => 'modified', 'additions' => 2, 'deletions' => 1, 'changes' => 3,
        ];
    }
}
