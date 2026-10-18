<?php

namespace App\Support\Projects;

use App\Models\Project;

final class ProjectLogo
{
    public static function url(Project $project): ?string
    {
        if (! $project->logo_path) {
            return null;
        }

        // A different generated filename on every replacement busts image caches.
        return route('projects.logo.show', $project).'?v='.rawurlencode(basename($project->logo_path));
    }
}
