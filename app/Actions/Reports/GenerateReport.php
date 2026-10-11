<?php

namespace App\Actions\Reports;

use App\Contracts\Ai\ReportTextGenerator;
use App\Enums\ReportFormat;
use App\Enums\ReportSource;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\Reports\ReportGenerationContext;
use App\Support\Reports\WorkReportDocument;
use App\Support\Reports\ReportAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class GenerateReport
{
    public function __construct(
        private readonly ReportGenerationContext $context,
        private readonly ReportTextGenerator $generator,
        private readonly WorkReportDocument $workReport,
    ) {}

    public function handle(User $actor, Report $report, string $language = 'en'): Report
    {
        $report->loadMissing('project');
        abort_unless(app(ReportAccess::class)->canEdit($actor, $report), 403);
        $this->assertEditable($report->project);
        if ($report->published_at !== null) {
            throw new InvalidArgumentException('Move this report to draft before regenerating it.');
        }

        if (! preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', $language) || strlen($language) > 35) {
            throw new InvalidArgumentException('Select a valid language code, such as en, pl or pt-BR.');
        }

        if ($report->format === ReportFormat::WORK_REPORT && ! in_array(ReportSource::WORKLOGS->value, $report->sources, true)) {
            throw new InvalidArgumentException('Work Report requires Worklogs as a source.');
        }

        $previousBody = $report->body;
        $previousGenerated = $report->generated_at?->toIso8601String();

        // Build context and request AI outside the database transaction.
        $context = $this->context->build($report);
        $body = trim($this->generator->generate($context, $language));
        if ($body === '' || mb_strlen($body) > 50000) {
            throw new InvalidArgumentException('AI returned an invalid report. Try again later.');
        }

        if ($report->format === ReportFormat::WORK_REPORT) {
            $body = $this->workReport->render($context, $body, $language);
        }
        if (mb_strlen($body) > 50000) {
            throw new InvalidArgumentException('Report content is too long. Choose a shorter period.');
        }

        return DB::transaction(function () use ($actor, $report, $language, $previousBody, $previousGenerated, $body): Report {
            $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            $project = Project::query()->whereKey($locked->project_id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ReportAccess::class)->canEdit($actor, $locked), 403);
            $this->assertEditable($project);
            if ($locked->published_at !== null) {
                throw new InvalidArgumentException('Move this report to draft before regenerating it.');
            }
            if ($locked->body !== $previousBody || $locked->generated_at?->toIso8601String() !== $previousGenerated) {
                throw new InvalidArgumentException('This report was updated while AI was generating. Reload and try again.');
            }

            $locked->fill([
                'body' => $body,
                'generated_locale' => $language,
                'generated_at' => now(),
                'edited_at' => null,
            ])->save();

            return $locked;
        });
    }

    private function assertEditable(Project $project): void
    {
        if ($project->archived_at !== null) {
            throw new InvalidArgumentException('Reports cannot be generated for archived projects.');
        }
    }
}
