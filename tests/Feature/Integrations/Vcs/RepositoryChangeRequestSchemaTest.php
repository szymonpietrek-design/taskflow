<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use App\Models\VcsInstance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepositoryChangeRequestSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_shared_table_is_additive_and_old_git_integration_tables_remain_available(): void
    {
        foreach (['repository_change_requests', 'repository_connections', 'repository_commits',
            'repository_sync_runs', 'user_vcs_accounts', 'github_repositories', 'github_commits'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertDatabaseCount('repository_change_requests', 0);
    }

    public function test_pull_request_metadata_round_trips_with_separate_provider_timestamps(): void
    {
        $repository = $this->repository('github', 'https://github.com', '100', '25');
        $request = $repository->changeRequests()->create($this->details([
            'external_change_request_id' => '900100',
            'number' => 42,
            'is_draft' => true,
            'is_locked' => true,
            'body' => "## Context\n".str_repeat('Long description ', 100),
            'author_external_user_id' => '77',
            'author_login' => 'alice',
            'remote_created_at' => '2026-10-20 09:30:00',
            'remote_updated_at' => '2026-10-21 10:00:00',
            'synced_at' => '2026-10-22 00:40:00',
        ]));

        $saved = RepositoryChangeRequest::query()->findOrFail($request->id);
        $this->assertSame($repository->id, $saved->repository->id);
        $this->assertSame(42, $saved->number);
        $this->assertTrue($saved->is_draft);
        $this->assertTrue($saved->is_locked);
        $this->assertSame(RepositoryChangeRequest::STATE_OPEN, $saved->state);
        $this->assertSame('77', $saved->author_external_user_id);
        $this->assertSame('2026-10-20 09:30:00', $saved->remote_created_at->toDateTimeString());
        $this->assertSame('2026-10-22 00:40:00', $saved->synced_at->toDateTimeString());
        $this->assertNotNull($saved->created_at);
        $this->assertNotNull($saved->updated_at);
        $this->assertCount(1, $repository->changeRequests);
    }

    public function test_same_numbers_and_external_ids_can_exist_in_different_repositories_and_instances(): void
    {
        $github = $this->repository('github', 'https://github.com', '101', '501');
        $gitlab = $this->repository('gitlab', 'https://gitlab.com', '101', '501');
        $selfHosted = $this->repository('gitlab', 'https://gitlab.example.test', '101', '501');

        foreach ([$github, $gitlab, $selfHosted] as $repository) {
            $repository->changeRequests()->create($this->details());
        }

        $this->assertDatabaseCount('repository_change_requests', 3);
        $this->assertSame(1, $github->changeRequests()->count());
        $this->assertSame(1, $gitlab->changeRequests()->count());
        $this->assertSame(1, $selfHosted->changeRequests()->count());
        $this->assertNotSame($gitlab->connection->instance->id, $selfHosted->connection->instance->id);
    }

    public function test_duplicate_external_id_is_rejected_within_repository(): void
    {
        $repository = $this->repository('github', 'https://github.com', '102', '502');
        $repository->changeRequests()->create($this->details());

        $this->expectException(QueryException::class);
        $repository->changeRequests()->create($this->details(['number' => 43]));
    }

    public function test_duplicate_display_number_is_rejected_within_repository(): void
    {
        $repository = $this->repository('github', 'https://github.com', '103', '503');
        $repository->changeRequests()->create($this->details());

        $this->expectException(QueryException::class);
        $repository->changeRequests()->create($this->details(['external_change_request_id' => '900200']));
    }

    public function test_closed_and_merged_states_are_distinct_and_merger_is_not_assumed_to_be_author(): void
    {
        $repository = $this->repository('github', 'https://github.com', '104', '504');
        $closed = $repository->changeRequests()->create($this->details([
            'state' => RepositoryChangeRequest::STATE_CLOSED,
            'closed_at' => '2026-10-21 09:00:00',
        ]));
        $merged = $repository->changeRequests()->create($this->details([
            'external_change_request_id' => '900300',
            'number' => 44,
            'state' => RepositoryChangeRequest::STATE_MERGED,
            'merged_at' => '2026-10-21 11:00:00',
            'merge_commit_sha' => str_repeat('b', 40),
            'author_external_user_id' => '77',
            'merged_by_external_user_id' => '88',
            'merged_by_login' => 'maintainer',
        ]));

        $this->assertNull($closed->fresh()->merged_at);
        $this->assertSame(RepositoryChangeRequest::STATE_MERGED, $merged->fresh()->state);
        $this->assertSame('88', $merged->fresh()->merged_by_external_user_id);
        $this->assertNotSame($merged->author_external_user_id, $merged->merged_by_external_user_id);
        $this->assertSame(str_repeat('b', 40), $merged->fresh()->merge_commit_sha);
    }

    public function test_deleting_one_repository_cascades_only_its_requests(): void
    {
        $one = $this->repository('github', 'https://github.com', '105', '505');
        $two = $this->repository('gitlab', 'https://gitlab.com', '106', '506');
        $one->changeRequests()->create($this->details());
        $two->changeRequests()->create($this->details());

        $one->delete();

        $this->assertDatabaseCount('repository_change_requests', 1);
        $this->assertSame(1, $two->changeRequests()->count());
        $this->assertDatabaseHas('repository_change_requests', ['repository_connection_id' => $two->id]);
        $this->assertDatabaseCount('repository_connections', 1);
        $this->assertDatabaseCount('vcs_instances', 2);
    }

    private function repository(string $provider, string $baseUrl, string $installationId, string $repoId): RepositoryConnection
    {
        $project = Project::factory()->create();
        $instance = VcsInstance::query()->firstOrCreate(
            ['provider' => $provider, 'instance_key' => hash('sha256', $baseUrl)],
            ['base_url' => $baseUrl],
        );
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => $instance->id,
            'external_connection_id' => $installationId,
            'account_login' => 'example',
            'connected_at' => now(),
        ]);

        return RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => $repoId,
            'full_name' => 'example/repository',
            'default_branch' => 'main',
            'start_sha' => self::SHA,
            'last_synced_sha' => self::SHA,
            'connected_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides
     *  @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return array_replace([
            'external_change_request_id' => '900100',
            'number' => 42,
            'title' => 'Improve Git integration',
            'body' => 'Updates the VCS integration.',
            'web_url' => 'https://github.com/example/repository/pull/42',
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'is_draft' => false,
            'source_branch' => 'feature/integration',
            'target_branch' => 'main',
            'head_sha' => self::SHA,
        ], $overrides);
    }
}
