<?php

namespace App\Actions\Releases;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Project;
use App\Models\Release;
use App\Models\User;
use App\Support\Releases\ReleaseVersion;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CreateRelease
{
    public function __construct(private readonly AttachCompletedTasks $attachCompletedTasks) {}

    public function handle(
        User $creator,
        Project $project,
        string $version,
        ?string $name = null,
        ?string $description = null,
        ?DateTimeInterface $plannedFor = null,
        bool $includeCompletedTasks = false,
    ): Release {
        Gate::forUser($creator)->authorize('update', $project);

        if ($project->type !== ProjectType::APPLICATION) {
            throw new InvalidArgumentException(__('Only application projects can have releases.'));
        }

        if ($project->archived_at !== null) {
            throw new InvalidArgumentException(__('Cannot create a release in an archived project.'));
        }

        $parsedVersion = ReleaseVersion::parse(trim($version));
        $name = $name !== null ? trim($name) : null;
        $description = $description !== null ? trim($description) : null;

        if ($name !== null && mb_strlen($name) > 160) {
            throw new InvalidArgumentException(__('Release name must not exceed 160 characters.'));
        }

        if ($project->releases()->where('version', $parsedVersion->toString())->exists()) {
            throw new InvalidArgumentException(__('This version already exists in the project.'));
        }

        // The database unique index remains the final guard against concurrent inserts.
        return DB::transaction(function () use ($creator, $project, $parsedVersion, $name, $description, $plannedFor, $includeCompletedTasks): Release {
            $release = $project->releases()->create([
                'created_by' => $creator->id,
                'version' => $parsedVersion->toString(),
                'version_major' => $parsedVersion->major,
                'version_minor' => $parsedVersion->minor,
                'version_patch' => $parsedVersion->patch,
                'name' => $name !== '' ? $name : null,
                'description' => $description !== '' ? $description : null,
                'status' => ReleaseStatus::DRAFT,
                'planned_for' => $plannedFor,
                'released_at' => null,
            ]);

            if ($includeCompletedTasks) {
                $this->attachCompletedTasks->handle($creator, $release);
            }

            return $release;
        });
    }
}
