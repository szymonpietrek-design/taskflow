<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Integrations\GitHub\GitHubAppClient;
use App\Integrations\GitHub\GitHubRepositorySynchronizer;
use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProjectGitHubController extends Controller
{
    public function index(Request $request, Project $project, GitHubAppClient $github): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        $connected = GitHubRepository::query()
            ->where('project_id', $project->id)
            ->with('installation:id,installation_id,account_login')
            ->first();

        return Inertia::render('projects/integrations/GitHub', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'is_archived' => $project->archived_at !== null,
            ],
            'configured' => $github->configured(),
            'can_connect' => $request->user()->can('manageManagers', $project),
            'repository' => $connected ? [
                'full_name' => $connected->full_name,
                'default_branch' => $connected->default_branch,
                'installation_id' => $connected->installation->installation_id,
                'account_login' => $connected->installation->account_login,
                'start_sha' => $connected->start_sha,
                'last_synced_sha' => $connected->last_synced_sha,
                'connected_at' => $connected->connected_at?->toIso8601String(),
                'last_synced_at' => $connected->last_synced_at?->toIso8601String(),
            ] : null,
            // Existing workspace installation may be reused by another project in the same workspace.
            'commits' => $connected ? $connected->commits()
                ->orderByDesc('committed_at')->orderByDesc('id')->limit(15)->get()
                ->map(fn ($commit) => [
                    'sha' => $commit->sha,
                    'subject' => explode("\n", $commit->message, 2)[0],
                    'author' => $commit->author_name ?? $commit->author_login,
                    'committed_at' => $commit->committed_at?->toIso8601String(),
                ])->values() : [],
            'commits_count' => $connected ? $connected->commits()->count() : 0,
            'sync_runs' => $connected ? $connected->syncRuns()
                ->orderByDesc('id')->limit(5)->get()
                ->map(fn ($run) => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'imported_count' => $run->imported_count,
                    'fetched_count' => $run->fetched_count,
                    'error_message' => $run->error_message,
                    'started_at' => $run->started_at?->toIso8601String(),
                ])->values() : [],
            'installations' => $request->user()->can('manageManagers', $project)
                ? GitHubInstallation::query()->where('workspace_id', $project->workspace_id)
                    ->orderBy('account_login')->get(['installation_id', 'account_login'])
                : [],
        ]);
    }

    public function store(Request $request, Project $project, GitHubAppClient $github): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        $validated = $request->validate([
            'installation_id' => ['required', 'integer', 'min:1'],
            'repository' => ['required', 'string', 'max:200', 'regex:~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D'],
        ]);
        $installationId = (int) $validated['installation_id'];

        if (GitHubRepository::query()->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['repository' => 'A repository is already linked. Disconnect it first.']);
        }

        // One GitHub App installation is assigned to only one TaskFlow workspace.
        $existingInstallation = GitHubInstallation::query()->where('installation_id', $installationId)->first();
        if ($existingInstallation && $existingInstallation->workspace_id !== $project->workspace_id) {
            throw ValidationException::withMessages(['installation_id' => 'This installation is already linked to another workspace.']);
        }
        // A numeric installation ID is not proof of ownership of a GitHub account.
        // Only the system operator can initially approve/assign a new installation;
        // workspace administrators may reuse installations already assigned to their workspace.
        if ($existingInstallation === null && ! $request->user()->is_system_admin) {
            throw ValidationException::withMessages([
                'installation_id' => 'Ask a system administrator to register this GitHub App installation for your workspace first.',
            ]);
        }
        if (! $github->configured()) {
            throw ValidationException::withMessages(['installation_id' => 'GitHub App is not configured on this server.']);
        }

        try {
            $installationData = $github->inspectInstallation($installationId);
            $repositoryData = $github->inspectRepository($installationId, $validated['repository']);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'repository' => 'Cannot access this repository with the specified GitHub App installation.',
            ]);
        }

        // GitHub only allows an installation to access repositories of its owner.
        // Still verify the owner in the metadata to avoid linking an unrelated repository.
        $repoOwner = explode('/', $repositoryData['full_name'], 2)[0];
        if (! hash_equals(strtolower($installationData['account_login']), strtolower($repoOwner))) {
            throw ValidationException::withMessages(['repository' => 'Repository owner does not match the GitHub App installation.']);
        }

        try {
            DB::transaction(function () use ($project, $installationId, $installationData, $repositoryData): void {
                $installation = GitHubInstallation::query()->firstOrCreate(
                    ['installation_id' => $installationId],
                    [
                        'workspace_id' => $project->workspace_id,
                        'github_account_id' => $installationData['github_account_id'],
                        'account_login' => $installationData['account_login'],
                        'account_type' => $installationData['account_type'],
                        'connected_at' => now(),
                    ],
                );
                if ($installation->workspace_id !== $project->workspace_id) {
                    throw ValidationException::withMessages(['installation_id' => 'Installation belongs to another workspace.']);
                }

                GitHubRepository::query()->create([
                    'project_id' => $project->id,
                    'github_installation_id' => $installation->id,
                    'github_repository_id' => $repositoryData['github_repository_id'],
                    'full_name' => $repositoryData['full_name'],
                    'default_branch' => $repositoryData['default_branch'],
                    'start_sha' => $repositoryData['head_sha'],
                    'last_synced_sha' => $repositoryData['head_sha'],
                    'connected_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['repository' => 'Connection already exists. Refresh the page.']);
        }

        return to_route('projects.github.index', $project);
    }

    public function sync(Request $request, Project $project, GitHubRepositorySynchronizer $synchronizer): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        $repository = GitHubRepository::query()->where('project_id', $project->id)->firstOrFail();

        try {
            $result = $synchronizer->synchronize($repository);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'sync' => app()->getLocale() === 'pl'
                    ? 'Synchronizacja nie powiodła się lub zmieniła się historia gałęzi. Zapisany SHA pozostał bez zmian. Sprawdź historię synchronizacji i spróbuj ponownie.'
                    : 'Synchronization failed or the branch history changed. The saved SHA was not advanced. Review the synchronization history and retry.',
            ]);
        }

        $imported = (int) $result['imported'];
        $isPolish = app()->getLocale() === 'pl';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isPolish
                ? ($imported > 0
                    ? 'Synchronizacja GitHub zakończona. Nowe commity: '.$imported.'.'
                    : 'Synchronizacja GitHub zakończona. Brak nowych commitów.')
                : ($imported > 0
                    ? 'Imported '.$imported.' new GitHub commits.'
                    : 'GitHub synchronization complete. No new commits.'),
        ]);

        return to_route('projects.github.index', $project);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        GitHubRepository::query()->where('project_id', $project->id)->delete();

        // Keep the workspace installation: other projects may use it.
        return to_route('projects.github.index', $project);
    }
}
