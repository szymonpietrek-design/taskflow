<?php

namespace App\Actions\Projects;

use App\Actions\Tasks\EnsureDefaultTaskStatuses;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Projects\ProjectIdentity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateProject
{
    public function __construct(
        private readonly EnsureDefaultTaskStatuses $ensureDefaultTaskStatuses,
    ) {}

    public function handle(
        User $creator,
        Workspace $workspace,
        string $name,
        string $key,
        ProjectType $type = ProjectType::STANDARD,
        ProjectVisibility $visibility = ProjectVisibility::PRIVATE,
        ?string $description = null,
        ?string $icon = null,
        ?string $color = null,
    ): Project {
        $name = trim($name);
        $key = Str::upper(trim($key));
        $description = $description !== null ? trim($description) : null;

        if ($name === '') {
            throw new InvalidArgumentException(__('Project name cannot be empty.'));
        }

        if (! preg_match('/^[A-Z][A-Z0-9]{0,11}$/', $key)) {
            throw new InvalidArgumentException(__('Project key must contain 1 to 12 uppercase letters or numbers and start with a letter.'));
        }

        $membership = $creator->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->whereNull('suspended_at')
            ->first();

        if (! in_array($membership?->role, [WorkspaceRole::OWNER, WorkspaceRole::ADMIN], true)) {
            throw new AuthorizationException('You are not allowed to create projects in this workspace.');
        }

        if (($icon !== null && ! in_array($icon, ProjectIdentity::ICONS, true))
            || ($color !== null && ! in_array($color, ProjectIdentity::COLORS, true))) {
            throw new InvalidArgumentException('Unsupported project icon or color.');
        }

        if ($workspace->projects()->where('key', $key)->exists()) {
            throw new InvalidArgumentException(__('Project key must be unique within the workspace.'));
        }

        return DB::transaction(function () use (
            $creator,
            $workspace,
            $name,
            $key,
            $type,
            $visibility,
            $description,
            $icon,
            $color,
        ): Project {
            $project = $workspace->projects()->create([
                'created_by' => $creator->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($workspace, $name),
                'key' => $key,
                'description' => $description !== '' ? $description : null,
                'icon' => $icon,
                'color' => $color,
                'type' => $type,
                'visibility' => $visibility,
                'task_sequence' => 0,
            ]);

            $this->ensureDefaultTaskStatuses->handle($project);

            return $project;
        });
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'project';
        $slug = $baseSlug;
        $suffix = 2;

        while ($workspace->projects()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
