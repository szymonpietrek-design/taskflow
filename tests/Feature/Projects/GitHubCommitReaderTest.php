<?php

namespace Tests\Feature\Projects;

use App\Integrations\GitHub\GitHubCommitReader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GitHubCommitReaderTest extends TestCase
{
    private const BASE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HEAD = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_reading_equal_head_does_not_call_compare_api(): void
    {
        $this->fakeRepository(self::BASE);
        $result = $this->reader()->changesSince(456, 'example/api', 'main', self::BASE, 123);
        $this->assertSame(self::BASE, $result['head_sha']);
        $this->assertSame([], $result['commits']);
        Http::assertNotSent(fn (Request $req): bool => str_contains($req->url(), '/compare/'));
    }

    public function test_imports_paginated_compare_without_previous_history(): void
    {
        $firstPage = [];
        for ($i = 1; $i <= 100; $i++) {
            $firstPage[] = $this->commit(str_pad(dechex($i), 40, '0', STR_PAD_LEFT));
        }
        $secondPage = [$this->commit(self::HEAD)];
        $this->fakeRepository(self::HEAD, [
            ['status' => 'ahead', 'ahead_by' => 101, 'behind_by' => 0, 'total_commits' => 101, 'commits' => $firstPage],
            ['status' => 'ahead', 'ahead_by' => 101, 'behind_by' => 0, 'total_commits' => 101, 'commits' => $secondPage],
        ]);
        $result = $this->reader()->changesSince(456, 'example/api', 'main', self::BASE, 123);
        $this->assertCount(101, $result['commits']);
        $this->assertSame(self::HEAD, $result['head_sha']);
        Http::assertSent(fn (Request $req): bool => str_contains($req->url(), 'page=2'));
    }

    public function test_diverged_history_does_not_return_a_new_cursor(): void
    {
        $this->fakeRepository(self::HEAD, [[
            'status' => 'diverged', 'ahead_by' => 2, 'behind_by' => 1,
            'total_commits' => 2, 'commits' => [$this->commit(self::HEAD)],
        ]]);
        $this->expectException(RuntimeException::class);
        $this->reader()->changesSince(456, 'example/api', 'main', self::BASE, 123);
    }

    private function reader(): GitHubCommitReader
    {
        return new class extends GitHubCommitReader
        {
            protected function installationToken(int $installationId): string
            {
                return 'token-for-test';
            }
        };
    }

    private function fakeRepository(string $head, ?array $comparePages = null): void
    {
        $routes = [
            'https://api.github.com/repos/example/api' => Http::response([
                'id' => 123, 'full_name' => 'example/api', 'default_branch' => 'main',
            ]),
            'https://api.github.com/repos/example/api/commits/main' => Http::response(['sha' => $head]),
        ];
        if ($comparePages !== null) {
            $sequence = Http::sequence();
            foreach ($comparePages as $page) {
                $sequence->push($page);
            }
            $routes['https://api.github.com/repos/example/api/compare/*'] = $sequence;
        }
        Http::fake($routes);
    }

    private function commit(string $sha): array
    {
        return [
            'sha' => $sha,
            'author' => ['login' => 'dev'],
            'commit' => [
                'message' => 'New change',
                'author' => ['name' => 'Developer'],
                'committer' => ['date' => '2026-10-21T09:00:00Z'],
            ],
        ];
    }
}
