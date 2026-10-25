<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\GitHub\GitHubCommitFileReader;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class GitHubCommitFilesTest extends TestCase
{
    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_reader_fetches_only_normalized_file_metadata_and_never_returns_patch(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->with(10)->andReturn('safe-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/commits/*' => Http::response([
                'sha' => self::SHA,
                'files' => [$this->file('app/Services/Report.php', [
                    'patch' => 'PRIVATE_SOURCE_CONTENT_NEVER_SUBMITTED',
                    'previous_filename' => 'old/Report.php',
                    'status' => 'renamed',
                ])],
            ]),
        ]);

        $result = $reader->filesForStoredCommits(10, 'example/repo', 20, [self::SHA]);
        $this->assertSame('renamed', $result[self::SHA][0]['status']);
        $this->assertSame('old/Report.php', $result[self::SHA][0]['previous_path']);
        $this->assertSame(hash('sha256', 'app/Services/Report.php'), $result[self::SHA][0]['path_hash']);
        $this->assertArrayNotHasKey('patch', $result[self::SHA][0]);
        $this->assertStringNotContainsString('PRIVATE_SOURCE_CONTENT_NEVER_SUBMITTED', json_encode($result));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/commits/'.self::SHA)
            && str_contains($request->url(), 'per_page=100'));
    }

    public function test_mismatched_repository_refuses_file_fetch(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('safe-test-token');
        Http::fake(['https://api.github.com/repos/example/repo' => Http::response(['id' => 99, 'full_name' => 'example/repo'])]);
        $this->expectExceptionMessage('repository identity mismatch');
        $reader->filesForStoredCommits(10, 'example/repo', 20, [self::SHA]);
    }

    public function test_more_than_supported_file_limit_is_not_silently_truncated(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('safe-test-token');
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/repos/example/repo') {
                return Http::response(['id' => 20, 'full_name' => 'example/repo']);
            }

            // The API paginates commit files. Simulate *different* paths on each
            // of three full pages so the reader reaches its 300-file safety cap.
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            if ($page < 1 || $page > 3) {
                return Http::response([], 404);
            }
            $start = ($page - 1) * 100 + 1;

            return Http::response([
                'sha' => self::SHA,
                'files' => array_map(fn ($i): array => $this->file('src/file'.$i.'.php'), range($start, $start + 99)),
            ]);
        });

        try {
            $reader->filesForStoredCommits(10, 'example/repo', 20, [self::SHA]);
            $this->fail('Expected the reader to reject an incomplete commit file snapshot.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('too many files', $exception->getMessage());
        }
        // One repository check plus exactly three different commit-file pages.
        Http::assertSentCount(4);
    }

    public function test_duplicate_path_across_commit_file_pages_is_rejected(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('safe-test-token');
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/repos/example/repo') {
                return Http::response(['id' => 20, 'full_name' => 'example/repo']);
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $paths = $page === 1
                ? range(1, 100)
                : [1]; // Duplicate src/file1.php from the previous page.

            return Http::response([
                'sha' => self::SHA,
                'files' => array_map(fn ($i): array => $this->file('src/file'.$i.'.php'), $paths),
            ]);
        });

        $this->expectExceptionMessage('Duplicate path across commit file pages');
        $reader->filesForStoredCommits(10, 'example/repo', 20, [self::SHA]);
    }

    public function test_unsafe_file_path_is_rejected(): void
    {
        $reader = $this->reader();
        $reader->shouldReceive('installationToken')->once()->andReturn('safe-test-token');
        Http::fake([
            'https://api.github.com/repos/example/repo' => Http::response(['id' => 20, 'full_name' => 'example/repo']),
            'https://api.github.com/repos/example/repo/commits/*' => Http::response([
                'sha' => self::SHA, 'files' => [$this->file('../private-file')],
            ]),
        ]);
        $this->expectExceptionMessage('Invalid commit file path');
        $reader->filesForStoredCommits(10, 'example/repo', 20, [self::SHA]);
    }

    private function reader(): Mockery\MockInterface
    {
        return Mockery::mock(GitHubCommitFileReader::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
    }

    private function file(string $path, array $overrides = []): array
    {
        return array_replace([
            'filename' => $path, 'status' => 'modified', 'additions' => 12,
            'deletions' => 4, 'changes' => 16, 'patch' => '@@ private change @@',
        ], $overrides);
    }
}
