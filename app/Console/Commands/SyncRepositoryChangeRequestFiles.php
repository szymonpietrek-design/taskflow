<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\RepositoryChangeRequestFileSynchronizer;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Console\Command;
use Throwable;

class SyncRepositoryChangeRequestFiles extends Command
{
    protected $signature = 'vcs:sync-pr-files {project : Numeric TaskFlow project ID} {--force : Refresh existing file snapshots}';
    protected $description = 'Sync bounded PR/MR changed-file snapshots for a connected repository';

    public function handle(RepositoryChangeRequestFileSynchronizer $synchronizer): int
    {
        $projectId = (string) $this->argument('project');
        if (! ctype_digit($projectId) || (int) $projectId < 1) {
            $this->error('Provide a valid project ID.');
            return self::FAILURE;
        }
        $project = Project::query()->find((int) $projectId);
        $repository = $project === null ? null : RepositoryConnection::query()->where('project_id', $project->id)->first();
        if ($repository === null) {
            $this->error('This project has no connected repository.');
            return self::FAILURE;
        }
        try {
            $result = $synchronizer->synchronize($repository, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('PR file sync failed. Check the server log and GitHub App permissions.');
            return self::FAILURE;
        }
        $this->info(sprintf('PR files: checked %d, files %d, remaining %d.',
            $result['checked'], $result['files'], $result['remaining']));
        return self::SUCCESS;
    }
}
