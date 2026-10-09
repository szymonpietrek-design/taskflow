<?php

namespace App\Actions\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Models\ReleaseChangelog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PublishReleaseChangelog
{
    public function handle(User $actor, Release $release): ReleaseChangelog
    {
        Gate::forUser($actor)->authorize('update', $release->project);

        return DB::transaction(function () use ($actor, $release): ReleaseChangelog {
            $locked = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            if ($locked->project->type !== ProjectType::APPLICATION
                || $locked->project->archived_at !== null
                || $locked->status !== ReleaseStatus::RELEASED) {
                throw new InvalidArgumentException('Publish the release before publishing its changelog.');
            }

            $changelog = $locked->changelog()->lockForUpdate()->first();
            if ($changelog === null || trim($changelog->draft_body ?? '') === '') {
                throw new InvalidArgumentException('Save a non-empty changelog draft before publishing.');
            }
            if ($changelog->published_body === $changelog->draft_body
                && $changelog->published_locale === $changelog->draft_locale) {
                throw new InvalidArgumentException('The published changelog is already up to date.');
            }

            $changelog->fill([
                'published_body' => $changelog->draft_body,
                'published_locale' => $changelog->draft_locale,
                'published_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            return $changelog;
        });
    }
}
