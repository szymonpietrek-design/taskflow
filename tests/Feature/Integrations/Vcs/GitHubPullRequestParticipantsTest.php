<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestReader;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GitHubPullRequestParticipantsTest extends TestCase
{
    public function test_merged_by_is_read_from_individual_pull_request_details(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->with(10, ['pull_requests' => 'read'])->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/2' => Http::response([
                'number' => 2, 'merged' => true, 'merged_at' => '2026-10-22T10:00:00Z',
                'merged_by' => ['id' => 555, 'login' => 'current-login'],
            ]),
        ]);

        $this->assertSame([2 => ['external_user_id' => '555', 'login' => 'current-login']],
            $reader->mergedByForStoredPullRequests(10, 'example/repo', 20, [2]));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/example/repo/pulls/2');
    }

    public function test_unknown_merging_account_is_not_inferred_from_pr_author(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/2' => Http::response([
                'number' => 2, 'merged' => true, 'merged_at' => '2026-10-22T10:00:00Z',
                'merged_by' => null, 'user' => ['id' => 456, 'login' => 'author'],
            ]),
        ]);
        $this->assertSame([2 => ['external_user_id' => null, 'login' => null]],
            $reader->mergedByForStoredPullRequests(10, 'example/repo', 20, [2]));
    }

    public function test_fails_closed_if_remote_repo_identity_does_not_match(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake(['https://api.github.com/repos/example/repo' => Http::response(['id' => 200, 'full_name' => 'example/repo'])]);
        $this->expectExceptionMessage('repository identity changed');
        $reader->mergedByForStoredPullRequests(10, 'example/repo', 20, [2]);
    }

    public function test_rejects_unmerged_or_mismatched_detail(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/2' => Http::response([
                'number' => 2, 'merged' => false, 'merged_at' => null, 'merged_by' => null,
            ]),
        ]);
        $this->expectException(RuntimeException::class);
        $reader->mergedByForStoredPullRequests(10, 'example/repo', 20, [2]);
    }

    public function test_invalid_batch_does_not_contact_github(): void
    {
        $reader = $this->reader();
        $reader->shouldNotReceive('installationToken');
        $this->expectException(RuntimeException::class);
        $reader->mergedByForStoredPullRequests(10, 'example/repo', 20, [2, 2]);
    }

    private function reader(): \Mockery\MockInterface
    {
        return Mockery::mock(GitHubPullRequestReader::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
    }
}
