<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestReviewReader;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GitHubPullRequestReviewsTest extends TestCase
{
    public function test_reads_reviews_only_with_pull_requests_read_permission(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()
            ->with(10, ['pull_requests' => 'read'])->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/3/reviews*' => Http::response([
                $this->review(101, 'APPROVED'),
                $this->review(102, 'CHANGES_REQUESTED'),
                $this->review(103, 'DISMISSED'),
            ]),
        ]);
        $result = $reader->reviewsForStoredPullRequests(10, 'example/repo', 20, [3]);
        $this->assertCount(3, $result[3]);
        $this->assertSame('APPROVED', $result[3][0]['state']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pulls/3/reviews'));
    }

    public function test_rejects_mismatched_repository_before_review_call(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake(['https://api.github.com/repos/example/repo' => Http::response(['id' => 999, 'full_name' => 'example/repo'])]);
        $this->expectExceptionMessage('repository identity changed');
        $reader->reviewsForStoredPullRequests(10, 'example/repo', 20, [3]);
    }

    public function test_invalid_batch_never_contacts_github(): void
    {
        $reader = $this->reader();
        $reader->shouldNotReceive('installationToken');
        $this->expectException(RuntimeException::class);
        $reader->reviewsForStoredPullRequests(10, 'example/repo', 20, [3, 3]);
    }

    public function test_fails_closed_if_review_pagination_exceeds_500(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/repos/example/repo') {
                return Http::response(['id' => 20, 'full_name' => 'example/repo']);
            }
            return Http::response(array_fill(0, 100, $this->review(101, 'COMMENTED')));
        });
        $this->expectExceptionMessage('exceeds 500');
        $reader->reviewsForStoredPullRequests(10, 'example/repo', 20, [3]);
    }

    public function test_http_error_fails_without_silently_returning_empty_reviews(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/3/reviews*' => Http::response(['message' => 'Forbidden'], 403),
        ]);
        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $reader->reviewsForStoredPullRequests(10, 'example/repo', 20, [3]);
    }

    private function reader(): Mockery\MockInterface
    {
        return Mockery::mock(GitHubPullRequestReviewReader::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
    }

    private function review(int $id, string $state): array
    {
        return [
            'id' => $id, 'state' => $state,
            'user' => ['id' => 45, 'login' => 'reviewer'],
            'body' => 'Looks good', 'commit_id' => str_repeat('a', 40),
            'submitted_at' => '2026-10-22T10:00:00Z',
        ];
    }
}
