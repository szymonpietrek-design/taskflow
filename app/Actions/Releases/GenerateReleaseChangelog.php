<?php

namespace App\Actions\Releases;

use App\Contracts\Ai\ChangelogTextGenerator;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Models\ReleaseChangelog;
use App\Models\User;
use App\Support\Releases\ReleaseChangelogContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class GenerateReleaseChangelog
{
    public function __construct(
        private readonly ReleaseChangelogContext $context,
        private readonly ChangelogTextGenerator $generator,
    ) {}

    public function handle(User $actor, Release $release, string $language = 'en'): ReleaseChangelog
    {
        Gate::forUser($actor)->authorize('update', $release->project);
        $this->assertEditable($release);
        if (! preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', $language) || strlen($language) > 35) {
            throw new InvalidArgumentException(__('Select a valid language code, such as en, pl, de or pt-BR.'));
        }

        $originalDraft = $release->changelog?->draft_body ?? '';
        $source = $this->context->build($release);
        // External AI call is made outside any DB transaction.
        $markdown = trim($this->generator->generate($source, $language));
        if ($markdown === '' || mb_strlen($markdown) > 50000) {
            throw new InvalidArgumentException(__('AI returned an invalid changelog draft.'));
        }

        return DB::transaction(function () use ($actor, $release, $originalDraft, $markdown, $language): ReleaseChangelog {
            $locked = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($locked);

            $changelog = $locked->changelog()->lockForUpdate()->firstOrNew();
            if (($changelog->draft_body ?? '') !== $originalDraft) {
                throw new InvalidArgumentException(__('Changelog draft changed while AI was generating. Reload and try again.'));
            }

            $changelog->release_id = $locked->id;
            $changelog->fill(['draft_body' => $markdown, 'draft_locale' => $language, 'updated_by' => $actor->id]);
            // published_body and published_at are intentionally left untouched.
            $changelog->save();

            return $changelog;
        });
    }

    private function assertEditable(Release $release): void
    {
        $release->loadMissing('project');
        if ($release->project->type !== ProjectType::APPLICATION
            || $release->project->archived_at !== null
            || $release->status === ReleaseStatus::CANCELED) {
            throw new InvalidArgumentException(__('Cannot generate a changelog for this release.'));
        }
    }
}
