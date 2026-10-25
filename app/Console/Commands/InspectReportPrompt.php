<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\Ai\ReportPromptArchive;
use App\Services\Ai\ReportPromptBuilder;
use App\Support\Reports\ReportGenerationContext;
use Illuminate\Console\Command;
use Throwable;

/** Offline snapshot of the precise system/user messages used by PrismReportGenerator. */
final class InspectReportPrompt extends Command
{
    protected $signature = 'ai:inspect-report-prompt
        {report : Report database ID}
        {--language=pl : Language code (e.g. pl, en)}';

    protected $description = 'Export private DeepSeek report prompt without calling the API (local only)';

    public function handle(
        ReportGenerationContext $contextBuilder,
        ReportPromptBuilder $promptBuilder,
        ReportPromptArchive $archive,
    ): int {
        if (! app()->environment('local')) {
            $this->error('Prompt inspection is available only with APP_ENV=local.');
            return self::FAILURE;
        }

        $report = Report::query()->find((int) $this->argument('report'));
        if ($report === null) {
            $this->error('Report not found.');
            return self::FAILURE;
        }

        try {
            $language = (string) $this->option('language');
            $prompts = $promptBuilder->build($contextBuilder->build($report), $language);
            $path = $archive->save(
                $report->id, $language, (string) config('services.ai_chat.model', 'deepseek-chat'),
                max(200, min(8000, (int) config('services.ai_chat.report_max_tokens', 4000))),
                $prompts['system'], $prompts['user'],
            );
        } catch (Throwable $exception) {
            // No raw exception: it could contain confidential report content.
            $this->error('Unable to build prompt snapshot ('.class_basename($exception).').');
            return self::FAILURE;
        }

        $this->info('Saved private report prompt: '.$path);
        $this->line('System bytes: '.strlen($prompts['system']));
        $this->line('User bytes: '.strlen($prompts['user']));
        $this->warn('Contains unredacted report data. Do not publish, commit or share without review.');
        $this->comment('No request was sent to DeepSeek.');
        return self::SUCCESS;
    }
}
