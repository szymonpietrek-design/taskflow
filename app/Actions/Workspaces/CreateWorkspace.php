<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateWorkspace
{
    public function handle(User $creator, string $name): Workspace
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(__('Workspace name cannot be empty.'));
        }

        return DB::transaction(function () use ($creator, $name): Workspace {
            $workspace = Workspace::query()->create([
                'created_by' => $creator->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);

            $workspace->memberships()->create([
                'user_id' => $creator->id,
                'role' => WorkspaceRole::OWNER,
                'joined_at' => now(),
            ]);

            return $workspace;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'workspace';
        $slug = $baseSlug;
        $suffix = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
