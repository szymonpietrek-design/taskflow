<?php

namespace App\Support\Reports;

use App\Models\Report;
use InvalidArgumentException;

/**
 * Edits the saved Markdown document, regardless of report format.
 * A manual edit updates only the report snapshot and never the source Worklogs.
 */
final class ReportEditableContent
{
    public function editable(Report $report): ?string
    {
        return $report->body;
    }

    public function replace(Report $report, string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException(__('Report content cannot be empty.'));
        }
        if (mb_strlen($content) > 50000) {
            throw new InvalidArgumentException(__('Report content is too long.'));
        }

        return $content;
    }
}
