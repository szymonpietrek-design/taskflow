<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\RepositoryChangeRequestSynchronizer;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Console\Command;
use Throwable;

/** Operator-only manual entry point until the PR/MR UI is introduced in 3B.4. */
final class SyncRepositoryChangeRequests extends Command
{
    protected $signature = 'vcs:sync-pulls {project : TaskFlow project ID}';

    protected $description = 'Synchronize GitHub PR metadata without changing the commit sync cursor';

    public function handle(RepositoryChangeRequestSynchronizer $synchronizer): int
    {
        $projectId = (string) $this->argument('project');
        if (! ctype_digit($projectId) || (int) $projectId < 1) {
            $this->error('Provide a valid numeric project ID.');
            return self::FAILURE;
        }
        $project = Project::query()->find((int) $projectId);
        $repository = $project === null ? null : RepositoryConnection::query()->where('project_id', $project->id)->first();
        if ($repository === null) {
            $this->error('This project has no VCS repository connection.');
            return self::FAILURE;
        }

        try {
            $result = $synchronizer->synchronize($repository);
        } catch (Throwable $exception) {
            report($exception);
            // Do not echo remote API responses or credential-bearing exception details.
            $this->error('Pull request synchronization failed. Check the server log and GitHub App permissions.');
            return self::FAILURE;
        }
        $this->info('Pull requests: fetched '.$result['fetched'].', created '.$result['created'].', updated '.$result['updated'].'.');
        return self::SUCCESS;
    }
}
