<?php

namespace App\Actions\Reports;

use App\Models\Project;
use App\Models\Report;
use App\Models\User;

final class CreateReport
{
    /**
     * @param array{title:string,period_start:string,period_end:string,sources:array<int,string>} $data
     */
    public function handle(User $user, Project $project, array $data): Report
    {
        return $project->reports()->create([
            'created_by' => $user->id,
            'title' => trim($data['title']),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'sources' => $data['sources'],
        ]);
    }
}
