<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\UpdateReportContentRequest;
use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportAccess;
use App\Support\Reports\ReportEditableContent;
use App\Support\Reports\ReportRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class ReportContentController extends Controller
{
    public function __invoke(UpdateReportContentRequest $request, Project $project, Report $report, ReportEditableContent $editor): RedirectResponse
    {
        abort_unless($report->project_id === $project->id, 404);
        $input = $request->validated();

        DB::transaction(function () use ($request, $project, $report, $editor, $input): void {
            $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ReportAccess::class)->canEdit($request->user(), $locked), 403);
            abort_if($project->fresh()->archived_at !== null || $locked->published_at !== null, 403);
            if ($locked->body === null || $locked->generated_at === null) {
                throw ValidationException::withMessages(['content' => 'Generate the report before editing it.']);
            }
            if (app(ReportRevision::class)->token($locked) !== $input['version']) {
                throw ValidationException::withMessages(['content' => 'This report was changed. Reload to see the latest version.']);
            }
            try {
                $body = $editor->replace($locked, $input['content']);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['content' => $exception->getMessage()]);
            }
            if ($body !== $locked->body) {
                $locked->forceFill(['body' => $body, 'edited_at' => now()])->save();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report saved.')]);

        return to_route('reports.show', [$project, $report]);
    }
}
