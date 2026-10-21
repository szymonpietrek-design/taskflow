<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\VcsIntegrityAuditor;
use Illuminate\Console\Command;

/** No network calls, migrations, synchronization or credential output. */
class AuditVcsData extends Command
{
    protected $signature = 'vcs:audit';

    protected $description = 'Check VCS links, GitHub cutover and synchronization status without changing data';

    public function handle(VcsIntegrityAuditor $auditor): int
    {
        $result = $auditor->inspect();
        foreach ($result['counts'] as $name => $count) {
            $this->line("{$name}: {$count}");
        }

        if ($result['issues'] !== []) {
            foreach ($result['issues'] as $issue) {
                $this->error($issue);
            }
            $this->error('VCS audit found inconsistencies. No records were changed.');

            return self::FAILURE;
        }

        $this->info('VCS audit passed. No records were changed.');

        return self::SUCCESS;
    }
}
