<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\BackfillGitHubLegacyData;
use Illuminate\Console\Command;

class BackfillGitHubVcsData extends Command
{
    protected $signature = 'vcs:backfill-github';

    protected $description = 'Copy recent and legacy GitHub records to provider-neutral VCS tables without deleting originals';

    public function handle(BackfillGitHubLegacyData $backfill): int
    {
        foreach ($backfill->run() as $type => $count) {
            $this->line("{$type}: {$count}");
        }

        $this->info('GitHub VCS data copied. Original github_* tables are unchanged.');

        return self::SUCCESS;
    }
}
