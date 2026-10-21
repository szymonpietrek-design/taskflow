<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\Vcs\BackfillGitHubLegacyData;
use App\Models\GitHubAccount;
use App\Models\GitHubCommit;
use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use App\Models\GitHubSyncRun;
use App\Models\Project;
use App\Models\User;
use App\Models\UserVcsAccount;
use App\Models\VcsInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GitHubVcsBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const NEXT_SHA = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_existing_github_data_is_copied_without_touching_old_records_or_resetting_sync_cursor(): void
    {
        [$project, $user, $repo, $commit, $account] = $this->legacyFixtures();

        app(BackfillGitHubLegacyData::class)->run(true);

        $instance = VcsInstance::query()->sole();
        $this->assertSame('github', $instance->provider);
        $this->assertSame('https://github.com', $instance->base_url);
        $this->assertSame(hash('sha256', 'https://github.com'), $instance->instance_key);
        $this->assertDatabaseHas('vcs_connections', [
            'legacy_github_installation_id' => $repo->installation->id,
            'workspace_id' => $project->workspace_id,
            'external_connection_id' => '456',
            'external_account_id' => '78',
        ]);
        $this->assertDatabaseHas('repository_connections', [
            'project_id' => $project->id,
            'legacy_github_repository_id' => $repo->id,
            'external_repository_id' => '123',
            'start_sha' => self::BASE_SHA,
            'last_synced_sha' => self::NEXT_SHA,
        ]);
        $this->assertDatabaseHas('repository_commits', [
            'legacy_github_commit_id' => $commit->id,
            'sha' => self::NEXT_SHA,
            'message' => 'Implementation details',
            'author_login' => 'old-login',
            'author_external_user_id' => '54321',
        ]);
        $this->assertNotNull(DB::table('repository_commits')->first()->author_identity_checked_at);
        $this->assertDatabaseHas('repository_sync_runs', [
            'legacy_github_sync_run_id' => GitHubSyncRun::query()->sole()->id,
            'from_sha' => self::BASE_SHA,
            'to_sha' => self::NEXT_SHA,
            'fetched_count' => 2,
            'imported_count' => 1,
            'status' => 'ok',
        ]);
        $this->assertDatabaseHas('user_vcs_accounts', [
            'legacy_github_account_id' => $account->id,
            'user_id' => $user->id,
            'external_user_id' => '54321',
            'login' => 'new-login',
        ]);
        $this->assertSame($repo->connected_at->toDateTimeString(),
            DB::table('repository_connections')->first()->connected_at);

        // Legacy queries still back the running GitHub UI in 3A.5.1.
        $this->assertDatabaseCount('github_installations', 1);
        $this->assertDatabaseCount('github_repositories', 1);
        $this->assertDatabaseCount('github_commits', 1);
        $this->assertDatabaseCount('github_sync_runs', 1);
        $this->assertDatabaseCount('github_accounts', 1);
        $this->assertSame(self::NEXT_SHA, $repo->fresh()->last_synced_sha);
        $this->assertSame('new-login', $user->fresh()->githubAccount->github_login);
    }

    public function test_backfill_can_run_more_than_once_and_catch_up_new_commits_and_cursor_changes(): void
    {
        [, , $repo] = $this->legacyFixtures();
        $service = app(BackfillGitHubLegacyData::class);
        $first = $service->run(true);
        $this->assertSame(['installations' => 1, 'repositories' => 1, 'commits' => 1,
            'sync_runs' => 1, 'accounts' => 1], $first);
        $this->assertSame($first, $service->run(true));

        $repo->forceFill(['last_synced_sha' => self::BASE_SHA])->save();
        GitHubCommit::query()->create([
            'github_repository_id' => $repo->id,
            'sha' => self::BASE_SHA,
            'message' => 'Additional commit while migrating',
            'author_name' => 'Unknown author',
            'author_login' => 'same-looking-login',
            // Without a verified GitHub ID this stays unknown.
            'author_github_user_id' => null,
            'author_identity_checked_at' => now(),
        ]);

        $this->assertSame(2, $service->run(true)['commits']);
        $this->assertDatabaseCount('repository_connections', 1);
        $this->assertDatabaseCount('repository_commits', 2);
        $this->assertDatabaseHas('repository_connections', [
            'legacy_github_repository_id' => $repo->id,
            'last_synced_sha' => self::BASE_SHA,
        ]);
        $this->assertDatabaseHas('repository_commits', [
            'sha' => self::BASE_SHA,
            'author_external_user_id' => null,
        ]);
        $this->assertDatabaseCount('github_commits', 2);
    }

    public function test_github_accounts_are_scoped_to_provider_instance_and_other_instances_can_reuse_external_ids(): void
    {
        [, $user] = $this->legacyFixtures();
        app(BackfillGitHubLegacyData::class)->run(true);

        $gitlab = VcsInstance::query()->create([
            'provider' => 'gitlab',
            'base_url' => 'https://gitlab.com',
            'instance_key' => hash('sha256', 'https://gitlab.com'),
        ]);
        $selfHosted = VcsInstance::query()->create([
            'provider' => 'gitlab',
            'base_url' => 'https://gitlab.example.test',
            'instance_key' => hash('sha256', 'https://gitlab.example.test'),
        ]);
        foreach ([$gitlab, $selfHosted] as $instance) {
            UserVcsAccount::query()->create([
                'user_id' => $user->id,
                'vcs_instance_id' => $instance->id,
                'external_user_id' => '54321',
                'login' => 'developer',
                'connected_at' => now(),
            ]);
        }
        $this->assertDatabaseCount('vcs_instances', 3);
        $this->assertDatabaseCount('user_vcs_accounts', 3);
        $this->assertSame(3, UserVcsAccount::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, UserVcsAccount::query()
            ->where('vcs_instance_id', VcsInstance::query()->where('provider', 'github')->value('id'))
            ->where('external_user_id', '54321')->count());
    }

    public function test_schema_is_additive_and_old_github_tables_remain_available(): void
    {
        foreach (['github_app_settings', 'github_installations', 'github_repositories', 'github_commits',
            'github_sync_runs', 'github_accounts', 'vcs_instances', 'vcs_connections',
            'repository_connections', 'repository_commits', 'repository_sync_runs', 'user_vcs_accounts'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    /** @return array{Project, User, GitHubRepository, GitHubCommit, GitHubAccount} */
    private function legacyFixtures(): array
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $installation = GitHubInstallation::query()->create([
            'workspace_id' => $project->workspace_id,
            'installation_id' => 456,
            'github_account_id' => 78,
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => '2026-10-20 10:11:12',
        ]);
        $repo = GitHubRepository::query()->create([
            'project_id' => $project->id,
            'github_installation_id' => $installation->id,
            'github_repository_id' => 123,
            'full_name' => 'example/repository',
            'default_branch' => 'main',
            'start_sha' => self::BASE_SHA,
            'last_synced_sha' => self::NEXT_SHA,
            'connected_at' => '2026-10-20 10:11:12',
            'last_synced_at' => '2026-10-20 10:31:12',
        ]);
        $commit = GitHubCommit::query()->create([
            'github_repository_id' => $repo->id,
            'sha' => self::NEXT_SHA,
            'message' => 'Implementation details',
            'author_name' => 'Someone',
            'author_login' => 'old-login',
            'author_github_user_id' => 54321,
            'author_identity_checked_at' => '2026-10-20 10:30:00',
            'committed_at' => '2026-10-20 10:25:00',
        ]);
        GitHubSyncRun::query()->create([
            'github_repository_id' => $repo->id,
            'from_sha' => self::BASE_SHA,
            'to_sha' => self::NEXT_SHA,
            'fetched_count' => 2,
            'imported_count' => 1,
            'status' => 'ok',
            'started_at' => '2026-10-20 10:30:00',
            'finished_at' => '2026-10-20 10:31:12',
        ]);
        $account = GitHubAccount::query()->create([
            'user_id' => $user->id,
            'github_user_id' => 54321,
            'github_login' => 'new-login',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/54321',
            'connected_at' => '2026-10-20 10:15:00',
        ]);

        return [$project, $user, $repo, $commit, $account];
    }
}
