<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportAccess;
use App\Support\Reports\ReportDocument;
use App\Support\Reports\ReportPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ReportPdfController extends Controller
{
    public function __invoke(Request $request, Project $project, Report $report, ReportDocument $document, ReportPdfRenderer $renderer): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        abort_unless($report->project_id === $project->id, 404);
        abort_unless(app(ReportAccess::class)->canView($request->user(), $report), 403);
        abort_if(blank($report->body), 404);

        $html = $document->view($report, $report->published_at === null)->render();
        $pdf = $renderer->render($html, $report->generated_locale ?: 'en');
        $name = Str::slug($report->title) ?: 'report';
        $filename = Str::limit($name, 80, '').'-'.$report->id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
