<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\Ai\PullRequestAiContextBuilder;
use App\Models\Project;
use Illuminate\Console\Command;

/** Local metadata-only diagnostic; never prints code or calls an AI service. */
final class PreviewPullRequestAiContext extends Command
{
    protected $signature = 'vcs:preview-ai-context
        {project : TaskFlow project ID}
        {number : Pull/Merge Request number}
        {--patches : Screen candidate patches IN MEMORY, but never print them}';

    protected $description = 'Preview VCS evidence counts and safety screening without sending or printing code';

    public function handle(PullRequestAiContextBuilder $builder): int
    {
        $projectId = (string) $this->argument('project');
        $number = (string) $this->argument('number');
        if (! ctype_digit($projectId) || (int) $projectId < 1
            || ! ctype_digit($number) || (int) $number < 1) {
            $this->error('Specify positive numeric project ID and PR number.');
            return self::FAILURE;
        }

        $project = Project::query()->find((int) $projectId);
        if ($project === null) {
            $this->error('Unknown project ID.');
            return self::FAILURE;
        }
        try {
            $context = $builder->build($project, (int) $number, (bool) $this->option('patches'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->error('No stored repository/PR for this project.');
            return self::FAILURE;
        }
        $this->line('Snapshot: '.$context['files_snapshot']['status']);
        $this->line('File records: '.$context['files_count']);
        $this->line('File entries in context: '.count($context['files']));
        $this->line('Submitted review entries: '.count($context['reviews']));
        $this->line('Recent repository commits (NOT PR commits): '.count($context['recent_repository_commits_unrelated_to_pr']));
        $this->line('Candidate patches included in memory: '.$context['patches_included']);
        $this->line('Screened/omitted patches by reason: '.json_encode($context['patches_skipped_by_reason']));
        // Differentiate PR evidence from optional, unrelated repository context.
        // In particular, limiting the commit list must not imply stale PR file snapshots.
        if ($context['files_snapshot']['status'] !== 'current') {
            $this->warn('PR file snapshot is missing or stale; no file entries or patches were included.');
        }
        if ($context['files_omitted']) {
            $this->warn('PR file list capped at '.$context['limits']['files'].' entries; additional PR files were omitted.');
        }
        if ($context['reviews_omitted']) {
            $this->warn('Submitted review list capped at '.$context['limits']['reviews'].' entries; additional reviews were omitted.');
        }
        if ($context['repository_commits_omitted']) {
            $this->comment('Recent repository commits capped at '.$context['limits']['repository_commits']
                .' (not PR-specific). This does not affect the PR file snapshot.');
        }
        if ((bool) $this->option('patches') && $context['patches_skipped_by_reason'] !== []) {
            $this->warn('Some optional patch candidates were screened out; no rejected code was included.');
        }
        $this->info('Local preview only. No code was printed, logged, stored or sent to AI.');
        return self::SUCCESS;
    }
}
