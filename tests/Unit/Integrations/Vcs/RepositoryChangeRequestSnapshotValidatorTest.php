<?php

namespace Tests\Unit\Integrations\Vcs;

use App\Integrations\Vcs\RepositoryChangeRequestSnapshotValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RepositoryChangeRequestSnapshotValidatorTest extends TestCase
{
    public function test_accepts_valid_open_and_merged_snapshots(): void
    {
        $validator = new RepositoryChangeRequestSnapshotValidator();
        $validator->assertValid($this->snapshot());
        $validator->assertValid(array_replace($this->snapshot(), [
            'state' => 'merged', 'merged_at' => '2026-10-22 12:00:00',
        ]));
        $this->addToAssertionCount(2);
    }

    public function test_rejects_invalid_provider_snapshots_before_database_write(): void
    {
        $changes = [
            ['number' => 0],
            ['state' => 'UNKNOWN'],
            ['is_draft' => 'no'],
            ['title' => ''],
            ['web_url' => 'javascript:alert(1)'],
            ['web_url' => 'https://user:password@example.com/pull/1'],
            ['author_external_user_id' => 'a-user-name'],
            ['head_sha' => 'not-a-sha'],
            ['remote_updated_at' => null],
            ['remote_updated_at' => '2026-19-22 10:00:00'],
            ['merged_at' => '2026-10-22 12:00:00'],
            ['state' => 'merged'], // missing merged_at
        ];

        foreach ($changes as $change) {
            try {
                (new RepositoryChangeRequestSnapshotValidator())->assertValid(array_replace($this->snapshot(), $change));
                $this->fail('Expected invalid snapshot: '.json_encode($change));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'external_change_request_id' => '123',
            'number' => 1,
            'title' => 'Example PR',
            'body' => 'Description',
            'web_url' => 'https://github.com/example/repo/pull/1',
            'state' => 'open',
            'is_draft' => false,
            'is_locked' => false,
            'source_branch' => 'feature',
            'target_branch' => 'main',
            'source_repository' => 'example/repo',
            'head_sha' => str_repeat('a', 40),
            'base_sha' => str_repeat('b', 40),
            'merge_commit_sha' => null,
            'author_external_user_id' => '123',
            'author_login' => 'example',
            'remote_created_at' => '2026-10-21 10:00:00',
            'remote_updated_at' => '2026-10-22 10:00:00',
            'closed_at' => null,
            'merged_at' => null,
        ];
    }
}
