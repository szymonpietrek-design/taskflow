<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\Reports\ReportDocument;
use Illuminate\Http\Response;

final class PublicReportController extends Controller
{
    public function __invoke(Report $report, string $token, ReportDocument $document): Response
    {
        // A revoked, draft, or archived report must be indistinguishable from a missing one.
        abort_unless($report->published_at !== null && filled($report->body), 404);
        abort_unless($report->share_token !== null && hash_equals($report->share_token, $token), 404);
        abort_if($report->project->archived_at !== null, 404);

        return response($document->view($report, false, publicHtml: true)->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ]);
    }
}
