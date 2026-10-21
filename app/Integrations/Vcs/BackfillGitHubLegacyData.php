<?php

namespace App\Integrations\Vcs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Re-runnable, read-only-to-GitHub data copy for the staged VCS migration.
 * All existing github_* records remain in place until the provider cut-over.
 *
 * Every batch uses UPSERT keyed by its stable legacy primary key; this lets
 * the next stage catch up commits and cursors added after the first migration.
 */
final class BackfillGitHubLegacyData
{
    private const BATCH_SIZE = 200;

    /** @return array<string, int> Counts of copied records, not newly inserted rows. */
    public function run(bool $allowAfterCutover = false): array
    {
        foreach (['github_installations', 'github_repositories', 'github_commits', 'github_sync_runs',
            'github_accounts', 'vcs_instances', 'vcs_connections', 'repository_connections',
            'repository_commits', 'repository_sync_runs', 'user_vcs_accounts'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new LogicException("Missing VCS table: {$table}. Run database migrations first.");
            }
        }

        $instanceId = $this->githubInstanceId();
        // After cutover the old GitHub cursor can no longer be trusted as live.
        if (! $allowAfterCutover && Schema::hasColumn('vcs_instances', 'cutover_at')
            && DB::table('vcs_instances')->where('id', $instanceId)->whereNotNull('cutover_at')->exists()) {
            throw new LogicException('GitHub already uses VCS tables; legacy backfill is disabled after cutover.');
        }
        $this->copyInstallations($instanceId);
        $this->copyRepositories();
        $this->copyCommits();
        $this->copySyncRuns();
        $this->copyUserAccounts($instanceId);

        return [
            'installations' => DB::table('vcs_connections')->whereNotNull('legacy_github_installation_id')->count(),
            'repositories' => DB::table('repository_connections')->whereNotNull('legacy_github_repository_id')->count(),
            'commits' => DB::table('repository_commits')->whereNotNull('legacy_github_commit_id')->count(),
            'sync_runs' => DB::table('repository_sync_runs')->whereNotNull('legacy_github_sync_run_id')->count(),
            'accounts' => DB::table('user_vcs_accounts')->whereNotNull('legacy_github_account_id')->count(),
        ];
    }

    private function githubInstanceId(): int
    {
        $key = hash('sha256', 'https://github.com');
        DB::table('vcs_instances')->insertOrIgnore([
            'provider' => 'github',
            'base_url' => 'https://github.com',
            'instance_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('vcs_instances')->where('provider', 'github')
            ->where('instance_key', $key)->value('id');
    }

    private function copyInstallations(int $instanceId): void
    {
        DB::table('github_installations')->orderBy('id')->chunkById(self::BATCH_SIZE, function ($rows) use ($instanceId): void {
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'vcs_instance_id' => $instanceId,
                    'workspace_id' => $row->workspace_id,
                    'external_connection_id' => (string) $row->installation_id,
                    'external_account_id' => (string) $row->github_account_id,
                    'account_login' => $row->account_login,
                    'account_type' => $row->account_type,
                    'connected_at' => $row->connected_at,
                    'legacy_github_installation_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            DB::table('vcs_connections')->upsert($records, ['legacy_github_installation_id'], [
                'vcs_instance_id', 'workspace_id', 'external_connection_id', 'external_account_id',
                'account_login', 'account_type', 'connected_at', 'legacy_github_installation_id', 'updated_at',
            ]);
        });
    }

    private function copyRepositories(): void
    {
        DB::table('github_repositories')->orderBy('id')->chunkById(self::BATCH_SIZE, function ($rows): void {
            $installations = $this->legacyIds('vcs_connections', 'legacy_github_installation_id',
                $rows->pluck('github_installation_id')->all());
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'project_id' => $row->project_id,
                    'vcs_connection_id' => $this->requiredId($installations, $row->github_installation_id, 'installation'),
                    'external_repository_id' => (string) $row->github_repository_id,
                    'full_name' => $row->full_name,
                    'default_branch' => $row->default_branch,
                    'start_sha' => $row->start_sha,
                    'last_synced_sha' => $row->last_synced_sha,
                    'connected_at' => $row->connected_at,
                    'last_synced_at' => $row->last_synced_at,
                    'legacy_github_repository_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            DB::table('repository_connections')->upsert($records, ['legacy_github_repository_id'], [
                'project_id', 'vcs_connection_id', 'external_repository_id', 'full_name', 'default_branch',
                'start_sha', 'last_synced_sha', 'connected_at', 'last_synced_at',
                'legacy_github_repository_id', 'updated_at',
            ]);
        });
    }

    private function copyCommits(): void
    {
        DB::table('github_commits')->orderBy('id')->chunkById(self::BATCH_SIZE, function ($rows): void {
            $repositories = $this->legacyIds('repository_connections', 'legacy_github_repository_id',
                $rows->pluck('github_repository_id')->all());
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'repository_connection_id' => $this->requiredId($repositories, $row->github_repository_id, 'repository'),
                    'sha' => $row->sha,
                    'message' => $row->message,
                    'author_name' => $row->author_name,
                    'author_login' => $row->author_login,
                    'author_external_user_id' => $row->author_github_user_id === null ? null : (string) $row->author_github_user_id,
                    'author_identity_checked_at' => $row->author_identity_checked_at,
                    'committed_at' => $row->committed_at,
                    'legacy_github_commit_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            DB::table('repository_commits')->upsert($records, ['legacy_github_commit_id'], [
                'repository_connection_id', 'sha', 'message', 'author_name', 'author_login',
                'author_external_user_id', 'author_identity_checked_at', 'committed_at',
                'legacy_github_commit_id', 'updated_at',
            ]);
        });
    }

    private function copySyncRuns(): void
    {
        DB::table('github_sync_runs')->orderBy('id')->chunkById(self::BATCH_SIZE, function ($rows): void {
            $repositories = $this->legacyIds('repository_connections', 'legacy_github_repository_id',
                $rows->pluck('github_repository_id')->all());
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'repository_connection_id' => $this->requiredId($repositories, $row->github_repository_id, 'repository'),
                    'from_sha' => $row->from_sha,
                    'to_sha' => $row->to_sha,
                    'fetched_count' => $row->fetched_count,
                    'imported_count' => $row->imported_count,
                    'status' => $row->status,
                    'error_message' => $row->error_message,
                    'started_at' => $row->started_at,
                    'finished_at' => $row->finished_at,
                    'legacy_github_sync_run_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            DB::table('repository_sync_runs')->upsert($records, ['legacy_github_sync_run_id'], [
                'repository_connection_id', 'from_sha', 'to_sha', 'fetched_count', 'imported_count',
                'status', 'error_message', 'started_at', 'finished_at',
                'legacy_github_sync_run_id', 'updated_at',
            ]);
        });
    }

    private function copyUserAccounts(int $instanceId): void
    {
        DB::table('github_accounts')->orderBy('id')->chunkById(self::BATCH_SIZE, function ($rows) use ($instanceId): void {
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'user_id' => $row->user_id,
                    'vcs_instance_id' => $instanceId,
                    'external_user_id' => (string) $row->github_user_id,
                    'login' => $row->github_login,
                    'avatar_url' => $row->avatar_url,
                    'connected_at' => $row->connected_at,
                    'legacy_github_account_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }
            DB::table('user_vcs_accounts')->upsert($records, ['legacy_github_account_id'], [
                'user_id', 'vcs_instance_id', 'external_user_id', 'login', 'avatar_url',
                'connected_at', 'legacy_github_account_id', 'updated_at',
            ]);
        });
    }

    /** @param array<int, int> $ids @return array<int, int> */
    private function legacyIds(string $table, string $column, array $ids): array
    {
        return DB::table($table)->whereIn($column, array_unique($ids))
            ->pluck('id', $column)->all();
    }

    /** @param array<int, int> $ids */
    private function requiredId(array $ids, int $legacyId, string $kind): int
    {
        if (! isset($ids[$legacyId])) {
            throw new LogicException("VCS backfill cannot locate {$kind} for legacy ID {$legacyId}.");
        }

        return (int) $ids[$legacyId];
    }
}
