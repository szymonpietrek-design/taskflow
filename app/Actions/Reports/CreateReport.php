<?php

namespace App\Actions\Reports;

use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;

final class CreateReport
{
    /**
     * @param array{title?:string|null,period_start:string,period_end:string,sources:array<int,string>} $data
     */
    public function handle(User $user, Project $project, array $data): Report
    {
        $title = trim((string) ($data['title'] ?? ''));

        return $project->reports()->create([
            'created_by' => $user->id,
            'title' => $title !== '' ? $title : $this->defaultTitle($data['period_start'], $data['period_end']),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'sources' => $data['sources'],
        ]);
    }

    private function defaultTitle(string $startDate, string $endDate): string
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

        return 'Project report · '.$period;
    }
}
