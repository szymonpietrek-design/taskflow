<?php

namespace App\Integrations\Vcs;

use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\RepositoryConnection;
use Illuminate\Support\Facades\DB;

/** Read-only check of the shared VCS schema after the GitHub cutover. */
final class VcsIntegrityAuditor
{
    /** @return array{counts:array<string,int>,issues:list<string>} */
    public function inspect(): array
    {
        $counts = [];
        foreach (['vcs_instances', 'vcs_connections', 'repository_connections',
            'repository_commits', 'repository_sync_runs', 'user_vcs_accounts'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $issues = [];
        $githubInstances = DB::table('vcs_instances')->where('provider', 'github')->get();
        foreach ($githubInstances as $instance) {
            if ($instance->base_url !== GitHubInstance::BASE_URL
                || $instance->instance_key !== GitHubInstance::key()) {
                $issues[] = "GitHub instance #{$instance->id} has an unexpected server identity.";
            }
        }

        RepositoryConnection::query()->with(['project:id,workspace_id',
            'connection:id,vcs_instance_id,workspace_id',
            'connection.instance:id,provider,base_url,instance_key,cutover_at'])
            ->chunkById(200, function ($repositories) use (&$issues): void {
                foreach ($repositories as $repository) {
                    $connection = $repository->connection;
                    $instance = $connection?->instance;
                    $project = $repository->project;
                    if ($connection === null || $instance === null || $project === null) {
                        $issues[] = "Repository #{$repository->id} has an incomplete project or provider link.";
                        continue;
                    }
                    if ((int) $connection->workspace_id !== (int) $project->workspace_id) {
                        $issues[] = "Repository #{$repository->id} crosses workspace boundaries.";
                    }
                    if ($instance->provider === 'github') {
                        if ($instance->cutover_at === null) {
                            $issues[] = "GitHub repository #{$repository->id} has no completed cutover marker.";
                        }
                        foreach (['start_sha', 'last_synced_sha'] as $field) {
                            if (! is_string($repository->{$field})
                                || ! preg_match('/\A[a-f0-9]{40}\z/iD', $repository->{$field})) {
                                $issues[] = "GitHub repository #{$repository->id} has an invalid {$field}.";
                            }
                        }
                    }
                }
            });

        $stale = DB::table('repository_sync_runs')->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(15))->count();
        if ($stale > 0) {
            $issues[] = "There are {$stale} synchronization runs still marked running after 15 minutes.";
        }

        return ['counts' => $counts, 'issues' => $issues];
    }
}
