<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\RepositoryChangeRequestParticipantBackfiller;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Console\Command;
use Throwable;

/** Temporary CLI entry point; UI participant data arrives in 3B.4. */
final class BackfillRepositoryChangeRequestParticipants extends Command
{
    protected $signature = 'vcs:sync-pr-participants {project : TaskFlow project ID}';

    protected $description = 'Enrich at most ten stored merged PRs with verified GitHub merging identities';

    public function handle(RepositoryChangeRequestParticipantBackfiller $backfiller): int
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
            $this->error('This project has no VCS repository connection.');
            return self::FAILURE;
        }

        try {
            $result = $backfiller->backfill($repository);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pull request participant synchronization failed. Check Laravel logs and GitHub permissions.');
            return self::FAILURE;
        }
        $this->info('PR merging identities: checked '.$result['checked'].', remaining '.$result['remaining'].'.');
        return self::SUCCESS;
    }
}
