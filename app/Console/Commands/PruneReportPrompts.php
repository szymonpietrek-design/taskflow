<?php

namespace App\Console\Commands;

use App\Services\Ai\ReportPromptArchive;
use Illuminate\Console\Command;
use Throwable;

final class PruneReportPrompts extends Command
{
    protected $signature = 'ai:prune-report-prompts';

    protected $description = 'Delete expired local-only report prompt debug snapshots';

    public function handle(ReportPromptArchive $archive): int
    {
        if (! app()->environment('local')) {
            $this->error('Prompt archive cleanup is available only with APP_ENV=local.');

            return self::FAILURE;
        }

        try {
            $removed = $archive->prune();
        } catch (Throwable $exception) {
            // Never expose paths, file contents or exceptions containing report data.
            $this->error('Unable to clean prompt snapshots ('.class_basename($exception).').');

            return self::FAILURE;
        }

        $this->info("Removed {$removed} expired report prompt snapshot(s).");

        return self::SUCCESS;
    }
}
