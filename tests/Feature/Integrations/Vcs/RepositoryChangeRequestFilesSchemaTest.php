<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepositoryChangeRequestFilesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_tables_and_revision_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('repository_change_request_files'));
        $this->assertTrue(Schema::hasColumns('repository_change_requests', ['files_head_sha', 'files_synced_at']));
    }

    public function test_file_snapshots_are_removed_with_their_pull_request(): void
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id, 'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '17', 'account_login' => 'example', 'connected_at' => now(),
        ]);
        $repo = RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => '19', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('a', 40), 'connected_at' => now(),
        ]);
        $pr = $repo->changeRequests()->create([
            'external_change_request_id' => '111', 'number' => 1, 'title' => 'Change',
            'web_url' => 'https://github.com/example/repo/pull/1', 'state' => 'open',
        ]);
        $pr->files()->create([
            'path' => 'hello.php', 'path_hash' => hash('sha256', 'hello.php'),
            'status' => 'added', 'additions' => 1, 'deletions' => 0, 'changes' => 1,
        ]);
        $this->assertDatabaseCount('repository_change_request_files', 1);
        $pr->delete();
        $this->assertDatabaseCount('repository_change_request_files', 0);
    }
}
