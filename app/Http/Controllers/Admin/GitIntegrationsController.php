<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\GitHub\GitHubAppClient;
use Inertia\Inertia;
use Inertia\Response;

/** Server-wide integration catalog. Repository choice remains project-specific. */
class GitIntegrationsController extends Controller
{
    public function __invoke(GitHubAppClient $github): Response
    {
        return Inertia::render('admin/Git', [
            // Only safe configuration booleans; do not expose keys, tokens or installation data.
            'providers' => [
                ['key' => 'github', 'name' => 'GitHub', 'available' => true, 'configured' => $github->configured()],
                ['key' => 'gitlab', 'name' => 'GitLab', 'available' => false, 'configured' => false],
                ['key' => 'gitea', 'name' => 'Gitea', 'available' => false, 'configured' => false],
            ],
        ]);
    }
}
