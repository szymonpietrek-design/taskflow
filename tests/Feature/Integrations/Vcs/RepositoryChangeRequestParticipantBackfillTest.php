<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestReader;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryChangeRequestParticipantBackfiller;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RepositoryChangeRequestParticipantBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const CURSOR = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_backfills_known_merged_pull_and_does_not_repeat_lookup(): void
    {
        $repository = $this->repository();
        $pull = $this->pull($repository, 2);
        $reader = $this->reader();
        $reader->shouldReceive('mergedByForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [2])
            ->andReturn([2 => ['external_user_id' => '54321', 'login' => 'merger']]);

        $service = app(RepositoryChangeRequestParticipantBackfiller::class);
        $this->assertSame(['checked' => 1, 'remaining' => 0], $service->backfill($repository));
        $this->assertSame(['checked' => 0, 'remaining' => 0], $service->backfill($repository));
        $this->assertDatabaseHas('repository_change_requests', [
            'id' => $pull->id, 'merged_by_external_user_id' => '54321', 'merged_by_login' => 'merger',
        ]);
        $this->assertNotNull($pull->fresh()->merged_by_checked_at);
        $this->assertSame(self::CURSOR, $repository->fresh()->last_synced_sha);
    }

    public function test_unknown_merger_is_checked_once_without_inventing_identity(): void
    {
        $repository = $this->repository();
        $pull = $this->pull($repository, 2);
        $this->reader()->shouldReceive('mergedByForStoredPullRequests')->once()
            ->andReturn([2 => ['external_user_id' => null, 'login' => null]]);
        $service = app(RepositoryChangeRequestParticipantBackfiller::class);
        $this->assertSame(1, $service->backfill($repository)['checked']);
        $this->assertNull($pull->fresh()->merged_by_external_user_id);
        $this->assertNotNull($pull->fresh()->merged_by_checked_at);
        $this->assertSame(0, $service->backfill($repository)['checked']);
    }

    public function test_open_and_closed_unmerged_pulls_are_not_sent_to_github(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 2, ['state' => 'open', 'merged_at' => null]);
        $this->pull($repository, 3, ['state' => 'closed', 'merged_at' => null]);
        $this->reader()->shouldNotReceive('mergedByForStoredPullRequests');
        $this->assertSame(['checked' => 0, 'remaining' => 0],
            app(RepositoryChangeRequestParticipantBackfiller::class)->backfill($repository));
    }

    public function test_batch_is_bounded_to_ten_stored_pull_requests(): void
    {
        $repository = $this->repository();
        foreach (range(1, 11) as $number) {
            $this->pull($repository, $number);
        }
        $reader = $this->reader();
        $reader->shouldReceive('mergedByForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, range(1, 10))
            ->andReturn(array_fill_keys(range(1, 10), ['external_user_id' => '1', 'login' => 'merger']));
        $this->assertSame(['checked' => 10, 'remaining' => 1],
            app(RepositoryChangeRequestParticipantBackfiller::class)->backfill($repository));
    }

    public function test_incomplete_api_batch_never_partially_marks_requests_as_checked(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 1);
        $this->pull($repository, 2);
        $this->reader()->shouldReceive('mergedByForStoredPullRequests')->once()
            ->andReturn([1 => ['external_user_id' => '1', 'login' => 'merger']]);
        $this->expectExceptionMessage('Incomplete pull request participant lookup');
        try {
            app(RepositoryChangeRequestParticipantBackfiller::class)->backfill($repository);
        } finally {
            $this->assertSame(2, RepositoryChangeRequest::query()->whereNull('merged_by_checked_at')->count());
        }
    }

    public function test_invalid_github_instance_is_rejected_before_calling_api(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 2);
        $repository->connection->instance->forceFill(['base_url' => 'https://untrusted.test'])->save();
        $this->reader()->shouldNotReceive('mergedByForStoredPullRequests');
        $this->expectExceptionMessage('Unsupported GitHub instance');
        app(RepositoryChangeRequestParticipantBackfiller::class)->backfill($repository);
    }

    private function reader(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(GitHubPullRequestReader::class);
        $this->app->instance(GitHubPullRequestReader::class, $mock);
        return $mock;
    }

    private function repository(): RepositoryConnection
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '10',
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        return RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => '20',
            'full_name' => 'example/repo',
            'default_branch' => 'main',
            'start_sha' => self::CURSOR,
            'last_synced_sha' => self::CURSOR,
            'connected_at' => now(),
        ]);
    }

    private function pull(RepositoryConnection $repository, int $number, array $overrides = []): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create(array_replace([
            'external_change_request_id' => (string) (1000 + $number),
            'number' => $number,
            'title' => 'Merged pull '.$number,
            'web_url' => 'https://github.com/example/repo/pull/'.$number,
            'state' => RepositoryChangeRequest::STATE_MERGED,
            'merged_at' => '2026-10-22 10:00:00',
            'author_external_user_id' => '45',
            'author_login' => 'author',
        ], $overrides));
    }
}
