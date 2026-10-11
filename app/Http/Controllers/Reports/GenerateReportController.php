<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\GenerateReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GenerateReportRequest;
use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

final class GenerateReportController extends Controller
{
    public function __invoke(GenerateReportRequest $request, Project $project, Report $report, GenerateReport $generate): RedirectResponse
    {
        abort_unless($report->project_id === $project->id, 404);

        try {
            $language = $request->validated('language', str_replace('_', '-', app()->getLocale()));
            $generate->handle($request->user(), $report, $language);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ai_generation' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // Provider exceptions can include prompts and sensitive data. Never log or expose them.
            throw ValidationException::withMessages([
                'ai_generation' => 'Could not generate the report. Check the AI provider configuration or try again later.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report generated. Review it before sharing.')]);

        return to_route('reports.show', [$project, $report]);
    }
}
