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

final class SaveReleaseChangelog
{
    public function handle(User $actor, Release $release, string $draftBody): ReleaseChangelog
    {
        Gate::forUser($actor)->authorize('update', $release->project);

        return DB::transaction(function () use ($actor, $release, $draftBody): ReleaseChangelog {
            $locked = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            if ($locked->project->type !== ProjectType::APPLICATION
                || $locked->project->archived_at !== null
                || $locked->status === ReleaseStatus::CANCELED) {
                throw new InvalidArgumentException(__('Cannot edit a changelog for this release.'));
            }

            // A published snapshot must never be overwritten by editing the draft.
            $changelog = $locked->changelog()->firstOrNew();
            $changelog->release_id = $locked->id;
            $body = trim($draftBody);
            if (mb_strlen($body) > 50000) {
                throw new InvalidArgumentException(__('The changelog must not exceed 50000 characters.'));
            }
            if ($changelog->exists && $changelog->draft_body === $body) {
                return $changelog;
            }
            $changelog->fill([
                'draft_body' => $body,
                'updated_by' => $actor->id,
            ]);
            $changelog->save();

            return $changelog;
        });
    }
}
