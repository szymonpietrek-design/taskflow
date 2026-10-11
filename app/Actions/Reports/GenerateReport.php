<?php

namespace App\Actions\Reports;

use App\Contracts\Ai\ReportTextGenerator;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\Reports\ReportGenerationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class GenerateReport
{
    public function __construct(
        private readonly ReportGenerationContext $context,
        private readonly ReportTextGenerator $generator,
    ) {}

    public function handle(User $actor, Report $report, string $language = 'en'): Report
    {
        $report->loadMissing('project');
        Gate::forUser($actor)->authorize('update', $report->project);
        $this->assertEditable($report->project);

        if (! preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', $language) || strlen($language) > 35) {
            throw new InvalidArgumentException('Select a valid language code, such as en, pl or pt-BR.');
        }

        $previousBody = $report->body;
        $previousGenerated = $report->generated_at?->toIso8601String();

        // Build the context and call DeepSeek outside of the database transaction.
        $context = $this->context->build($report);
        $body = trim($this->generator->generate($context, $language));
        if ($body === '' || mb_strlen($body) > 50000) {
            throw new InvalidArgumentException('AI returned an invalid report. Try again later.');
        }

        return DB::transaction(function () use ($actor, $report, $language, $previousBody, $previousGenerated, $body): Report {
            $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            $project = Project::query()->whereKey($locked->project_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $project);
            $this->assertEditable($project);
            if ($locked->body !== $previousBody || $locked->generated_at?->toIso8601String() !== $previousGenerated) {
                throw new InvalidArgumentException('This report was updated while AI was generating. Reload and try again.');
            }

            $locked->fill([
                'body' => $body,
                'generated_locale' => $language,
                'generated_at' => now(),
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
