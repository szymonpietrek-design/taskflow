<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GitHubPullRequestReaderTest extends TestCase
{
    public function test_reader_requests_all_states_and_uses_pull_requests_read_permission(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->with(10, ['pull_requests' => 'read'])->andReturn('temporary-test-token');

        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls*' => Http::response([['id' => 12, 'updated_at' => '2026-10-22T08:00:00Z']]),
        ]);
        $result = $reader->listPullRequests(10, 'example/repo', 20);
        $this->assertCount(1, $result);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pulls?')
            && str_contains($request->url(), 'state=all')
            && str_contains($request->url(), 'sort=updated')
            && str_contains($request->url(), 'direction=desc')
        );
    }

    public function test_older_items_are_not_read_past_overlap_cutoff(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('temporary-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls*' => Http::response([
                ['id' => 1, 'updated_at' => '2026-10-22T08:00:00Z'],
                ['id' => 2, 'updated_at' => '2026-10-20T08:00:00Z'],
            ]),
        ]);
        $result = $reader->listPullRequests(10, 'example/repo', 20, Carbon::parse('2026-10-22 07:55:00 UTC'));
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    public function test_mismatched_repository_id_is_rejected_without_reading_pulls(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('temporary-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 999, 'full_name' => 'example/repo']),
        ]);
        $this->expectException(RuntimeException::class);
        $reader->listPullRequests(10, 'example/repo', 20);
    }

    public function test_repository_with_more_than_five_pages_is_not_partially_returned(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('temporary-test-token');
        Http::fake(function ($request) {
            if (! str_contains($request->url(), '/pulls?')) {
                return Http::response(['id' => 20, 'full_name' => 'example/repo']);
            }
            return Http::response(array_fill(0, 100, ['id' => 5, 'updated_at' => '2026-10-22T08:00:00Z']));
        });
        $this->expectExceptionMessage('500-item synchronization limit');
        $reader->listPullRequests(10, 'example/repo', 20);
    }

    public function test_single_pull_reader_requests_exact_number_after_repository_identity_check(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->with(10, ['pull_requests' => 'read'])
            ->andReturn('temporary-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/7' => Http::response(['id' => 71, 'number' => 7]),
        ]);
        $this->assertSame(7, $reader->pullRequest(10, 'example/repo', 20, 7)['number']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/pulls/7'));
    }

    public function test_single_pull_reader_rejects_mismatched_repository_before_loading_pr(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('temporary-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 99, 'full_name' => 'example/repo']),
        ]);
        try {
            $reader->pullRequest(10, 'example/repo', 20, 7);
            $this->fail('Expected a repository identity failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('identity changed', $e->getMessage());
            Http::assertNotSent(fn ($request) => str_contains($request->url(), '/pulls/7'));
        }
    }

    public function test_single_pull_reader_rejects_mismatched_pr_number(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('temporary-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/7' => Http::response(['id' => 71, 'number' => 8]),
        ]);
        $this->expectExceptionMessage('mismatched pull request');
        $reader->pullRequest(10, 'example/repo', 20, 7);
    }

    private function reader(): \Mockery\MockInterface
    {
        return Mockery::mock(GitHubPullRequestReader::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
    }
}
