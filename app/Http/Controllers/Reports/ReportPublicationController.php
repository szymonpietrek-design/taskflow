<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class ReportPublicationController extends Controller
{
    public function publish(Request $request, Project $project, Report $report): RedirectResponse
    {
        $this->change($request, $project, $report, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report published.')]);

        return to_route('reports.show', [$project, $report]);
    }

    public function unpublish(Request $request, Project $project, Report $report): RedirectResponse
    {
        $this->change($request, $project, $report, false);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report moved to draft.')]);

        return to_route('reports.show', [$project, $report]);
    }

    private function change(Request $request, Project $project, Report $report, bool $publish): void
    {
        abort_unless($report->project_id === $project->id, 404);
        DB::transaction(function () use ($request, $project, $report, $publish): void {
            $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ReportAccess::class)->canEdit($request->user(), $locked), 403);
            abort_if($project->fresh()->archived_at !== null, 403);
            if ($publish) {
                if ($locked->generated_at === null || blank($locked->body)) {
                    throw ValidationException::withMessages(['publication' => 'Generate a report before publishing.']);
                }
                if ($locked->published_at === null) {
                    $locked->forceFill(['published_at' => now(), 'published_by' => $request->user()->id])->save();
                }
            } elseif ($locked->published_at !== null) {
                $locked->forceFill(['published_at' => null, 'published_by' => null])->save();
            }
        });
    }
}
