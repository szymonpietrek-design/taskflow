<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\RepositoryCommitFileSynchronizer;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Console\Command;
use Throwable;

/** Backfill already imported commits, without reimporting history or changing its cursor. */
final class SyncRepositoryCommitFiles extends Command
{
    protected $signature = 'vcs:sync-commit-files {project : Numeric TaskFlow project ID} {--batches=1 : Maximum number of five-commit batches (1-20)}';

    protected $description = 'Fetch bounded changed-file metadata for previously imported VCS commits';

    public function handle(RepositoryCommitFileSynchronizer $synchronizer): int
    {
        $projectId = (string) $this->argument('project');
        $batches = (string) $this->option('batches');
        if (! ctype_digit($projectId) || (int) $projectId < 1
            || ! ctype_digit($batches) || (int) $batches < 1 || (int) $batches > 20) {
            $this->error('Provide a valid project ID and batches between 1 and 20.');
            return self::FAILURE;
        }
        $project = Project::query()->find((int) $projectId);
        $repository = $project === null ? null
            : RepositoryConnection::query()->where('project_id', $project->id)->first();
        if ($repository === null) {
            $this->error('This project has no VCS repository connection.');
            return self::FAILURE;
        }

        $checked = 0;
        $files = 0;
        $remaining = 0;
        try {
            for ($i = 0; $i < (int) $batches; $i++) {
                $result = $synchronizer->synchronize($repository);
                $checked += $result['checked'];
                $files += $result['files'];
                $remaining = $result['remaining'];
                if ($remaining === 0) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Commit file synchronization failed. Check server logs and GitHub App permissions.');
            return self::FAILURE;
        }

        $this->info(sprintf('Commit files: checked %d commits, %d files, %d commits remaining.',
            $checked, $files, $remaining));
        return self::SUCCESS;
    }
}
