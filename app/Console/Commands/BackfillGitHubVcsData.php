<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\BackfillGitHubLegacyData;
use Illuminate\Console\Command;
use LogicException;

class BackfillGitHubVcsData extends Command
{
    protected $signature = 'vcs:backfill-github';

    protected $description = 'Copy recent and legacy GitHub records to provider-neutral VCS tables without deleting originals';

    public function handle(BackfillGitHubLegacyData $backfill): int
    {
        try {
            $counts = $backfill->run();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
        foreach ($counts as $type => $count) {
            $this->line("{$type}: {$count}");
        }

        $this->info('GitHub VCS data copied. Original github_* tables are unchanged.');

        return self::SUCCESS;
    }
}
