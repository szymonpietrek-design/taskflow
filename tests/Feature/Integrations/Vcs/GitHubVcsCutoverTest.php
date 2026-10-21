<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\Vcs\BackfillGitHubLegacyData;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GitHubVcsCutoverTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const NEW = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_manual_legacy_backfill_cannot_roll_back_vcs_cursor_after_cutover(): void
    {
        $project = Project::factory()->create();
        $installation = GitHubInstallation::query()->create([
            'workspace_id' => $project->workspace_id,
            'installation_id' => 456,
            'github_account_id' => 78,
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => now(),
        ]);
        GitHubRepository::query()->create([
            'project_id' => $project->id,
            'github_installation_id' => $installation->id,
            'github_repository_id' => 123,
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => self::OLD,
            'last_synced_sha' => self::OLD,
            'connected_at' => now(),
        ]);

        // Simulate records imported during the final cutover migration.
        app(BackfillGitHubLegacyData::class)->run(true);
        $repository = RepositoryConnection::query()->sole();
        $repository->update(['last_synced_sha' => self::NEW]);

        $this->artisan('vcs:backfill-github')->assertExitCode(1);
        $this->assertSame(self::NEW, $repository->fresh()->last_synced_sha);
        $this->assertSame(self::OLD, GitHubRepository::query()->sole()->last_synced_sha);
        $this->assertNotNull(GitHubInstance::get()->cutover_at);
    }

    public function test_github_instance_is_identified_by_fixed_provider_and_host(): void
    {
        $instance = GitHubInstance::get();
        $this->assertSame('github', $instance->provider);
        $this->assertSame('https://github.com', $instance->base_url);
        $this->assertSame(hash('sha256', 'https://github.com'), $instance->instance_key);
        $this->assertNotNull(DB::table('vcs_instances')->where('id', $instance->id)->value('cutover_at'));
    }
}
