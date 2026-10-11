<?php

namespace App\Support\Reports;

use App\Models\Report;

/** Opaque optimistic-concurrency token, independent of DB timestamp precision. */
final class ReportRevision
{
    public function token(Report $report): string
    {
        return hash('sha256', json_encode([
            $report->body,
            $report->generated_at?->toISOString(),
            $report->edited_at?->toISOString(),
            $report->published_at?->toISOString(),
            $report->updated_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }
}
