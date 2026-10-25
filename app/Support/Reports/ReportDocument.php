<?php

namespace App\Support\Reports;

use App\Enums\ReportFormat;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\View\View;

final class ReportDocument
{
    public function view(Report $report, bool $draft, bool $publicHtml = false): View
    {
        $report->loadMissing('project');
        $people = $report->contributor_ids === null
            ? null
            : User::query()->whereIn('id', $report->contributor_ids)->orderBy('name')->pluck('name')->all();

        $pdfPresentation = new ReportPdfPresentation;
        $markdown = $publicHtml ? ($report->body ?? '') : $pdfPresentation->body($report);

        return view('reports.document', [
            'report' => $report,
            'projectName' => $report->project->name,
            'displayTitle' => $publicHtml ? $report->title : $pdfPresentation->title($report),
            'displayPeriod' => $publicHtml
                ? $report->period_start->format('Y-m-d').' – '.$report->period_end->format('Y-m-d')
                : $pdfPresentation->period($report),
            'detailedReport' => $report->format === ReportFormat::WORK_REPORT,
            'people' => $people,
            'locale' => $report->generated_locale ?: 'en',
            'draft' => $draft,
            'publicHtml' => $publicHtml,
            'contentHtml' => app(ReportMarkdownPresentation::class)->render($markdown, $report->format),
        ]);
    }
}
