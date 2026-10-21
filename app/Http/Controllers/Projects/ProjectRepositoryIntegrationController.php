<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Integrations\GitHub\GitHubAppClient;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Provider-neutral repository integration overview. Provider-specific actions remain in their controllers. */
class ProjectRepositoryIntegrationController extends Controller
{
    public function __invoke(Request $request, Project $project, GitHubAppClient $github): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);

        $repository = RepositoryConnection::query()
            ->where('project_id', $project->id)
            ->with('connection.instance')
            ->withCount('commits')
            ->first();

        $provider = $repository?->connection?->instance?->provider;

        return Inertia::render('projects/integrations/Repository', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'is_archived' => $project->archived_at !== null,
            ],
            'can_manage' => $request->user()->can('manageManagers', $project),
            'repository' => $repository ? [
                'provider' => $provider,
                'full_name' => $repository->full_name,
                'default_branch' => $repository->default_branch,
                'last_synced_at' => $repository->last_synced_at?->toIso8601String(),
                'commits_count' => $repository->commits_count,
            ] : null,
            'providers' => [
                ['key' => 'github', 'name' => 'GitHub', 'available' => true, 'configured' => $github->configured()],
                ['key' => 'gitlab', 'name' => 'GitLab', 'available' => false, 'configured' => false],
                ['key' => 'gitea', 'name' => 'Gitea', 'available' => false, 'configured' => false],
            ],
        ]);
    }
}
