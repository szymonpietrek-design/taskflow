<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestReviewReader;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryChangeRequestReviewSynchronizer;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RepositoryChangeRequestReviewSyncTest extends TestCase
{
    use RefreshDatabase;

    private const CURSOR = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_creates_history_and_keeps_commit_cursor_unchanged(): void
    {
        $repository = $this->repository();
        $pr = $this->pull($repository, 3);
        $this->reader()->shouldReceive('reviewsForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [3])
            ->andReturn([3 => [$this->review(101), $this->review(102, ['state' => 'CHANGES_REQUESTED'])]]);

        $result = app(RepositoryChangeRequestReviewSynchronizer::class)->synchronize($repository);
        $this->assertSame(['checked' => 1, 'fetched' => 2, 'created' => 2, 'updated' => 0, 'remaining' => 0], $result);
        $this->assertSame(2, $pr->reviews()->count());
        $this->assertNotNull($pr->fresh()->reviews_checked_at);
        $this->assertSame(self::CURSOR, $repository->fresh()->last_synced_sha);
    }

    public function test_force_refresh_is_idempotent_and_can_update_dismissed_review(): void
    {
        $repository = $this->repository();
        $pr = $this->pull($repository, 3);
        $reader = $this->reader();
        $reader->shouldReceive('reviewsForStoredPullRequests')->twice()->andReturn(
            [3 => [$this->review(101)]],
            [3 => [$this->review(101, ['state' => 'DISMISSED'])]],
        );
        $sync = app(RepositoryChangeRequestReviewSynchronizer::class);
        $this->assertSame(1, $sync->synchronize($repository)['created']);
        $this->assertSame(0, $sync->synchronize($repository)['checked']);
        $result = $sync->synchronize($repository, force: true);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('dismissed', $pr->reviews()->first()->state);
        $this->assertSame(1, $pr->reviews()->count());
    }

    public function test_unknown_reviewer_is_saved_unassigned_not_guessed_from_login(): void
    {
        $repository = $this->repository();
        $pr = $this->pull($repository, 3);
        $this->reader()->shouldReceive('reviewsForStoredPullRequests')->once()
            ->andReturn([3 => [$this->review(101, ['user' => null])]]);
        app(RepositoryChangeRequestReviewSynchronizer::class)->synchronize($repository);
        $this->assertNull($pr->reviews()->first()->reviewer_external_user_id);
        $this->assertNull($pr->reviews()->first()->reviewer_login);
    }

    public function test_empty_reviews_are_marked_checked_and_do_not_repeat_immediately(): void
    {
        $repository = $this->repository();
        $pr = $this->pull($repository, 3);
        $this->reader()->shouldReceive('reviewsForStoredPullRequests')->once()->andReturn([3 => []]);
        $sync = app(RepositoryChangeRequestReviewSynchronizer::class);
        $this->assertSame(0, $sync->synchronize($repository)['fetched']);
        $this->assertSame(0, $sync->synchronize($repository)['checked']);
        $this->assertNotNull($pr->fresh()->reviews_checked_at);
    }

    public function test_invalid_review_in_a_batch_rolls_back_all_rows(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 3);
        $this->pull($repository, 4);
        $this->reader()->shouldReceive('reviewsForStoredPullRequests')->once()
            ->andReturn([3 => [$this->review(101)], 4 => [$this->review(102, ['state' => 'BROKEN'])]]);
        $this->expectException(RuntimeException::class);
        try {
            app(RepositoryChangeRequestReviewSynchronizer::class)->synchronize($repository);
        } finally {
            $this->assertDatabaseCount('repository_change_request_reviews', 0);
            $this->assertSame(2, RepositoryChangeRequest::query()->whereNull('reviews_checked_at')->count());
        }
    }

    public function test_duplicate_review_id_fails_before_writes(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 3);
        $this->reader()->shouldReceive('reviewsForStoredPullRequests')->once()
            ->andReturn([3 => [$this->review(101), $this->review(101)]]);
        $this->expectExceptionMessage('Duplicate review ID');
        app(RepositoryChangeRequestReviewSynchronizer::class)->synchronize($repository);
    }

    public function test_only_ten_stored_prs_are_queried_per_batch(): void
    {
        $repository = $this->repository();
        foreach (range(1, 11) as $number) {
            $this->pull($repository, $number);
        }
        $this->reader()->shouldReceive('reviewsForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, range(1, 10))
            ->andReturn(array_fill_keys(range(1, 10), []));
        $result = app(RepositoryChangeRequestReviewSynchronizer::class)->synchronize($repository);
        $this->assertSame(10, $result['checked']);
        $this->assertSame(1, $result['remaining']);
    }

    public function test_untrusted_git_instance_never_receives_installation_token(): void
    {
        $repository = $this->repository();
        $this->pull($repository, 3);
        $repository->connection->instance->forceFill(['base_url' => 'https://untrusted.example'])->save();
        $this->reader()->shouldNotReceive('reviewsForStoredPullRequests');
        $this->expectExceptionMessage('Unsupported GitHub instance');
        app(RepositoryChangeRequestReviewSynchronizer::class)->synchronize($repository);
    }

    public function test_review_refresh_does_not_replace_existing_data_on_api_failure(): void
    {
        $repository = $this->repository();
        $pr = $this->pull($repository, 3);
        $reader = $this->reader();
        $reader->shouldReceive('reviewsForStoredPullRequests')->twice()->andReturnUsing(function (): array {
            static $calls = 0;
            if (++$calls === 1) {
                return [3 => [$this->review(101)]];
            }
            throw new RuntimeException('GitHub API failure');
        });
        $sync = app(RepositoryChangeRequestReviewSynchronizer::class);
        $sync->synchronize($repository);
        try {
            $sync->synchronize($repository, force: true);
            $this->fail('Expected API exception');
        } catch (RuntimeException $exception) {
            $this->assertSame(1, $pr->reviews()->count());
            $this->assertSame(self::CURSOR, $repository->fresh()->last_synced_sha);
        }
    }

    private function reader(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GitHubPullRequestReviewReader::class);
        $this->app->instance(GitHubPullRequestReviewReader::class, $mock);
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
            'default_branch' => 'main', 'start_sha' => self::CURSOR,
            'last_synced_sha' => self::CURSOR, 'connected_at' => now(),
        ]);
    }

    private function pull(RepositoryConnection $repository, int $number): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create([
            'external_change_request_id' => (string) (1000 + $number),
            'number' => $number, 'title' => 'PR '.$number,
            'web_url' => 'https://github.com/example/repo/pull/'.$number,
            'state' => 'open', 'remote_updated_at' => '2026-10-22 08:00:00',
        ]);
    }

    private function review(int $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id, 'state' => 'APPROVED', 'user' => ['id' => 45, 'login' => 'reviewer'],
            'body' => 'LGTM', 'submitted_at' => '2026-10-22T10:00:00Z',
            'commit_id' => str_repeat('b', 40),
        ], $overrides);
    }
}
