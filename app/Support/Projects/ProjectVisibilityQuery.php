<?php

namespace App\Support\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQL counterpart of ProjectPolicy::view(). Keep the behavioral parity tests
 * updated whenever the policy changes.
 *
 * A single query is preferable to checking the policy once per project on
 * listing and dashboard pages. Archived projects remain readable only by
 * active workspace owners/admins and explicit project managers.
 */
final class ProjectVisibilityQuery
{
    public static function visibleTo(Builder $projects, int $userId): Builder
    {
        return $projects
            ->whereDoesntHave('workspace.memberships', fn (Builder $members) => $members
                ->where('user_id', $userId)
                ->whereNotNull('suspended_at')
                ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]))
            ->where(function (Builder $access) use ($userId): void {
                $access->whereHas('workspace.memberships', fn (Builder $members) => $members
                    ->where('user_id', $userId)
                    ->whereNull('suspended_at')
                    ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]))
                    ->orWhere(function (Builder $byProject) use ($userId): void {
                        $byProject->whereHas('memberships', fn (Builder $members) => $members
                            ->where('user_id', $userId))
                            ->where(function (Builder $archive) use ($userId): void {
                                $archive->whereNull('archived_at')
                                    ->orWhereHas('memberships', fn (Builder $members) => $members
                                        ->where('user_id', $userId)
                                        ->where('role', ProjectRole::MANAGER->value));
                            });
                    });
            });
    }
}
