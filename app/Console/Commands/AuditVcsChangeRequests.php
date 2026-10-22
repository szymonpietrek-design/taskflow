<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\VcsChangeRequestAuditor;
use App\Models\Project;
use Illuminate\Console\Command;

/** VCS PR/MR diagnostic; does not mutate the database or contact GitHub. */
final class AuditVcsChangeRequests extends Command
{
    protected $signature = 'vcs:audit-prs {--project= : Limit checks to a TaskFlow project ID}';

    protected $description = 'Audit PR/MR and review integrity without changing any data';

    public function handle(VcsChangeRequestAuditor $auditor): int
    {
        $project = $this->option('project');
        if ($project !== null && (! ctype_digit((string) $project) || (int) $project < 1
            || ! Project::query()->whereKey((int) $project)->exists())) {
            $this->error('Invalid or unknown project ID.');
            return self::FAILURE;
        }

        $result = $auditor->inspect($project !== null ? (int) $project : null);
        foreach ($result['counts'] as $label => $count) {
            $this->line("{$label}: {$count}");
        }
        foreach ($result['issues'] as $issue) {
            $this->error($issue);
        }
        if ($result['issues'] !== []) {
            $this->error('PR/MR audit failed. No records were changed.');
            return self::FAILURE;
        }
        $this->info('PR/MR audit passed. No records were changed.');
        return self::SUCCESS;
    }
}
