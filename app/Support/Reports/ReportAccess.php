<?php

namespace App\Support\Reports;

use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Report visibility is stricter than project visibility for personal reports. */
final class ReportAccess
{
    public function canManageTeam(User $user, Project $project): bool
    {
        return Gate::forUser($user)->allows('update', $project);
    }

    public function canCreate(User $user, Project $project): bool
    {
        return $project->archived_at === null && Gate::forUser($user)->allows('view', $project);
    }

    public function canView(User $user, Report $report): bool
    {
        return Gate::forUser($user)->allows('view', $report->project)
            && ($this->canManageTeam($user, $report->project) || $this->isOwnPersonal($user, $report));
    }

    public function canEdit(User $user, Report $report): bool
    {
        return $this->canCreate($user, $report->project)
            && ($this->canManageTeam($user, $report->project) || $this->isOwnPersonal($user, $report));
    }

    public function isOwnPersonal(User $user, Report $report): bool
    {
        return $report->created_by === $user->id && $report->contributor_ids === [$user->id];
    }
}
