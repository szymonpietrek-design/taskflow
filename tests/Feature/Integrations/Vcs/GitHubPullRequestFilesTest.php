<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubPullRequestFileReader;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GitHubPullRequestFilesTest extends TestCase
{
    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_fetches_files_with_narrow_permission_and_discards_large_patch(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()
            ->with(10, ['pull_requests' => 'read'])->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/3' => Http::response(['number' => 3, 'head' => ['sha' => self::SHA]]),
            'https://api.github.com/repos/example/repo/pulls/3/files*' => Http::response([
                $this->file('src/example.php', ['patch' => '@@ -1 +1 @@\n-old\n+new']),
                $this->file('src/big.php', ['patch' => str_repeat('x', 12001)]),
            ]),
        ]);
        $result = $reader->filesForStoredPullRequests(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::SHA]]);
        $this->assertCount(2, $result[3]);
        $this->assertSame(hash('sha256', 'src/example.php'), $result[3][0]['path_hash']);
        $this->assertNotNull($result[3][0]['patch']);
        $this->assertNull($result[3][1]['patch']);
        $this->assertTrue($result[3][1]['patch_truncated']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pulls/3/files'));
    }

    public function test_invalid_stored_identity_is_rejected_before_authentication(): void
    {
        $reader = $this->reader();
        $reader->shouldNotReceive('installationToken');
        $this->expectException(RuntimeException::class);
        $reader->filesForStoredPullRequests(10, 'example/repo', 20, [['number' => 3, 'head_sha' => 'invalid']]);
    }

    public function test_repository_mismatch_prevents_pull_file_requests(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake(['https://api.github.com/repos/example/repo' => Http::response(['id' => 99, 'full_name' => 'example/repo'])]);
        $this->expectExceptionMessage('repository identity mismatch');
        $reader->filesForStoredPullRequests(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::SHA]]);
    }

    public function test_head_changed_during_fetch_is_rejected(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake(function ($request) {
            $url = $request->url();
            if ($url === 'https://api.github.com/repos/example/repo') {
                return Http::response(['id' => 20, 'full_name' => 'example/repo']);
            }
            if (str_contains($url, '/pulls/3/files')) {
                return Http::response([$this->file('a.php')]);
            }
            static $reads = 0;
            return Http::response(['number' => 3, 'head' => ['sha' => (++$reads === 1) ? self::SHA : str_repeat('b', 40)]]);
        });
        $this->expectExceptionMessage('HEAD changed');
        $reader->filesForStoredPullRequests(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::SHA]]);
    }

    public function test_more_than_five_hundred_files_fails_closed(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake(function ($request) {
            $url = $request->url();
            if ($url === 'https://api.github.com/repos/example/repo') {
                return Http::response(['id' => 20, 'full_name' => 'example/repo']);
            }
            if (str_contains($url, '/pulls/3/files')) {
                return Http::response(array_map(fn ($i) => $this->file('src/file'.$i.'.php'), range(1, 100)));
            }
            return Http::response(['number' => 3, 'head' => ['sha' => self::SHA]]);
        });
        $this->expectExceptionMessage('500-file limit');
        $reader->filesForStoredPullRequests(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::SHA]]);
    }

    public function test_untrusted_file_path_is_rejected(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/pulls/3' => Http::response(['number' => 3, 'head' => ['sha' => self::SHA]]),
            'https://api.github.com/repos/example/repo/pulls/3/files*' => Http::response([$this->file('../secret.env')]),
        ]);
        $this->expectExceptionMessage('file path');
        $reader->filesForStoredPullRequests(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::SHA]]);
    }

    private function reader(): Mockery\MockInterface
    {
        return Mockery::mock(GitHubPullRequestFileReader::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
    }

    private function file(string $path, array $overrides = []): array
    {
        return array_replace([
            'filename' => $path, 'status' => 'modified', 'additions' => 2,
            'deletions' => 1, 'changes' => 3, 'sha' => self::SHA,
            'patch' => '@@ -1 +1 @@',
        ], $overrides);
    }
}
