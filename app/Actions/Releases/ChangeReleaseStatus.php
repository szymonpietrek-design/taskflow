<?php

namespace App\Actions\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ChangeReleaseStatus
{
    public function handle(
        User $actor,
        Release $release,
        ReleaseStatus $target,
        ?DateTimeInterface $plannedFor = null,
    ): Release {
        Gate::forUser($actor)->authorize('update', $release->project);

        return DB::transaction(function () use ($release, $target, $plannedFor): Release {
            /** @var Release $lockedRelease */
            $lockedRelease = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            $project = $lockedRelease->project;

            if ($project->type !== ProjectType::APPLICATION || $project->archived_at !== null) {
                throw new InvalidArgumentException('Release status cannot be changed in this project.');
            }

            $allowed = match ($lockedRelease->status) {
                ReleaseStatus::DRAFT => [ReleaseStatus::PLANNED, ReleaseStatus::CANCELED],
                ReleaseStatus::PLANNED => [ReleaseStatus::RELEASED, ReleaseStatus::CANCELED],
                ReleaseStatus::RELEASED, ReleaseStatus::CANCELED => [],
            };

            if (! in_array($target, $allowed, true)) {
                throw new InvalidArgumentException('This release status transition is not allowed.');
            }

            $values = ['status' => $target];

            if ($target === ReleaseStatus::PLANNED && $plannedFor !== null) {
                $values['planned_for'] = $plannedFor;
            }

            if ($target === ReleaseStatus::RELEASED) {
                $values['released_at'] = now();
            }

            // No reopening a released/canceled version. In both remaining states,
            // released_at must stay empty until the release is published.
            if ($target !== ReleaseStatus::RELEASED) {
                $values['released_at'] = null;
            }

            $lockedRelease->forceFill($values)->save();

            return $lockedRelease->refresh();
        });
    }
}
