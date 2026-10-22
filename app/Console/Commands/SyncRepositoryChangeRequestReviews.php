<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\RepositoryChangeRequestReviewSynchronizer;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Console\Command;
use Throwable;

/** Manual entry point; reviews will be exposed in the project UI in stage 3B.4. */
final class SyncRepositoryChangeRequestReviews extends Command
{
    protected $signature = 'vcs:sync-pr-reviews {project : TaskFlow project ID} {--force : Refresh the oldest checked PRs now}';

    protected $description = 'Synchronize reviews for at most ten previously stored pull requests';

    public function handle(RepositoryChangeRequestReviewSynchronizer $synchronizer): int
    {
        $projectId = (string) $this->argument('project');
        if (! ctype_digit($projectId) || (int) $projectId < 1) {
            $this->error('Provide a valid numeric project ID.');
            return self::FAILURE;
        }
        $project = Project::query()->find((int) $projectId);
        $repository = $project === null ? null : RepositoryConnection::query()
            ->where('project_id', $project->id)->first();
        if ($repository === null) {
            $this->error('This project has no connected VCS repository.');
            return self::FAILURE;
        }
        try {
            $result = $synchronizer->synchronize($repository, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pull request review sync failed. Check Laravel logs and GitHub App permissions.');
            return self::FAILURE;
        }
        $this->info(sprintf('PR reviews: checked %d, fetched %d, created %d, updated %d, remaining %d.',
            $result['checked'], $result['fetched'], $result['created'], $result['updated'], $result['remaining']));
        return self::SUCCESS;
    }
}
