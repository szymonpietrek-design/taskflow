<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\Ai\RecoverStalledPullRequestAiAnalyses as Recovery;
use Illuminate\Console\Command;

/** Read-only by default; never prints generated content, prompts or error responses. */
final class RecoverStalledPullRequestAiAnalyses extends Command
{
    protected $signature = 'vcs:recover-ai-analyses
        {--project= : Limit inspection to a project ID}
        {--limit=100 : Maximum number of stale rows to inspect (1-500)}
        {--apply : Mark stalled requests failed (does not requeue)}';

    protected $description = 'Inspect or safely close stalled PR AI queue requests without calling AI';

    public function handle(Recovery $recovery): int
    {
        $project = $this->option('project');
        $limit = $this->option('limit');
        if (($project !== null && (! ctype_digit((string) $project) || (int) $project < 1))
            || ! ctype_digit((string) $limit)
            || (int) $limit < 1 || (int) $limit > 500) {
            $this->error('Invalid --project or --limit option.');
            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        $counts = $recovery->run($project === null ? null : (int) $project, $apply, (int) $limit);

        $this->line('Stalled pending: '.$counts['pending']);
        $this->line('Stalled running: '.$counts['running']);
        $this->line('Scanned: '.$counts['scanned']);
        $this->line('Marked failed: '.$counts['recovered']);
        $this->warn($apply
            ? 'No AI requests were requeued. A new analysis needs fresh explicit user consent.'
            : 'Preview only. Re-run with --apply to mark stalled analyses as failed.');

        return self::SUCCESS;
    }
}
