<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class ReportSharingController extends Controller
{
    public function store(Request $request, Project $project, Report $report): RedirectResponse
    {
        $this->change($request, $project, $report, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public link enabled.')]);

        return to_route('reports.show', [$project, $report]);
    }

    public function destroy(Request $request, Project $project, Report $report): RedirectResponse
    {
        $this->change($request, $project, $report, false);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public link revoked.')]);

        return to_route('reports.show', [$project, $report]);
    }

    private function change(Request $request, Project $project, Report $report, bool $enable): void
    {
        abort_unless($report->project_id === $project->id, 404);
        DB::transaction(function () use ($request, $project, $report, $enable): void {
            $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ReportAccess::class)->canEdit($request->user(), $locked), 403);
            abort_if($project->fresh()->archived_at !== null, 403);

            if ($enable) {
                if ($locked->published_at === null || blank($locked->body)) {
                    throw ValidationException::withMessages(['sharing' => 'Publish the report before sharing it.']);
                }
                if ($locked->share_token === null) {
                    $locked->forceFill(['share_token' => Str::random(48), 'shared_at' => now()])->save();
                }
            } else {
                $locked->forceFill(['share_token' => null, 'shared_at' => null])->save();
            }
        });
    }
}
