<?php

namespace App\Actions\Reports;

use App\Enums\ReportFormat;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;

final class CreateReport
{
    /**
     * @param  array{title?:string|null,format?:string,period_start:string,period_end:string,sources:array<int,string>,contributor_ids?:array<int,int>|null}  $data
     */
    public function handle(User $user, Project $project, array $data): Report
    {
        $title = trim((string) ($data['title'] ?? ''));

        return $project->reports()->create([
            'created_by' => $user->id,
            'format' => $data['format'] ?? ReportFormat::PROJECT_SUMMARY->value,
            'title' => $title !== '' ? $title : $this->defaultTitle($data['period_start'], $data['period_end'], $data['format'] ?? ReportFormat::PROJECT_SUMMARY->value),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'sources' => $data['sources'],
            'contributor_ids' => isset($data['contributor_ids']) ? array_map('intval', $data['contributor_ids']) : null,
        ]);
    }

    private function defaultTitle(string $startDate, string $endDate, string $format): string
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        if ($start->isSameDay($end)) {
            $period = $start->format('M j, Y');
        } elseif ($start->format('Y-m') === $end->format('Y-m')) {
            $period = $start->format('M j').'–'.$end->format('j, Y');
        } elseif ($start->year === $end->year) {
            $period = $start->format('M j').' – '.$end->format('M j, Y');
        } else {
            $period = $start->format('M j, Y').' – '.$end->format('M j, Y');
        }

        return ($format === ReportFormat::WORK_REPORT->value ? 'Work report · ' : 'Project report · ').$period;
    }
}
