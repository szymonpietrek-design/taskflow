<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\VcsIntegrityAuditor;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VcsIntegrityAuditorTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_audit_reports_healthy_connection_without_modifying_data(): void
    {
        $repository = $this->connectedRepository();
        $before = DB::table('repository_connections')->where('id', $repository->id)->first();

        $result = app(VcsIntegrityAuditor::class)->inspect();
        $this->assertSame([], $result['issues']);
        $this->assertSame(1, $result['counts']['repository_connections']);
        $this->assertEquals($before, DB::table('repository_connections')->where('id', $repository->id)->first());
        $this->artisan('vcs:audit')->expectsOutput('VCS audit passed. No records were changed.')
            ->assertExitCode(0);
    }

    public function test_audit_detects_workspace_mismatch_and_invalid_cursor(): void
    {
        $repository = $this->connectedRepository();
        $otherWorkspace = Project::factory()->create()->workspace_id;
        $repository->connection->update(['workspace_id' => $otherWorkspace]);
        $repository->update(['last_synced_sha' => 'not-a-valid-sha']);

        $result = app(VcsIntegrityAuditor::class)->inspect();
        $this->assertTrue(collect($result['issues'])->contains(fn ($issue) => str_contains($issue, 'crosses workspace')));
        $this->assertTrue(collect($result['issues'])->contains(fn ($issue) => str_contains($issue, 'invalid last_synced_sha')));
        $this->artisan('vcs:audit')->assertExitCode(1);
        $this->assertSame('not-a-valid-sha', $repository->fresh()->last_synced_sha);
    }

    public function test_audit_reports_stale_sync_runs_but_does_not_change_them(): void
    {
        $repository = $this->connectedRepository();
        $repository->syncRuns()->create([
            'from_sha' => self::SHA, 'status' => 'running',
            'started_at' => now()->subMinutes(30),
        ]);
        $result = app(VcsIntegrityAuditor::class)->inspect();
        $this->assertTrue(collect($result['issues'])->contains(fn ($issue) => str_contains($issue, 'running after 15 minutes')));
        $this->assertDatabaseHas('repository_sync_runs', ['status' => 'running']);
    }

    public function test_audit_flags_unknown_github_endpoint(): void
    {
        $this->connectedRepository();
        GitHubInstance::get()->update(['base_url' => 'https://github.example.invalid']);
        $result = app(VcsIntegrityAuditor::class)->inspect();
        $this->assertTrue(collect($result['issues'])->contains(fn ($issue) => str_contains($issue, 'unexpected server identity')));
    }

    private function connectedRepository(): RepositoryConnection
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '456',
            'account_login' => 'example',
            'connected_at' => now(),
        ]);

        return RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => '123',
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => self::SHA,
            'last_synced_sha' => self::SHA,
            'connected_at' => now(),
        ]);
    }
}
