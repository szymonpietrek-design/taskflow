<?php

namespace App\Http\Controllers\Projects;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Integrations\GitHub\GitHubAppClient;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryAuthorBackfiller;
use App\Integrations\Vcs\RepositoryCommitFileSynchronizer;
use App\Integrations\Vcs\RepositoryChangeRequestSynchronizer;
use App\Integrations\Vcs\RepositoryChangeRequestReviewSynchronizer;
use App\Integrations\Vcs\RepositoryChangeRequestParticipantResolver;
use App\Integrations\Vcs\RepositorySynchronizer;
use App\Integrations\Vcs\RepositorySyncLock;
use App\Models\Project;
use App\Models\RepositoryCommit;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use App\Models\UserVcsAccount;
use App\Models\VcsConnection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** GitHub-specific UI entry point backed by provider-neutral repository tables. */
class ProjectGitHubController extends Controller
{
    /** Log only safe diagnostics; provider exception text may include private source or tokens. */
    private function logProviderFailure(Project $project, string $operation, \Throwable $exception): void
    {
        Log::warning('github_repository_operation_failed', [
            'project_id' => $project->id,
            'operation' => $operation,
            'exception_class' => $exception::class,
            'provider_http_status' => $exception instanceof RequestException
                ? $exception->response->status()
                : null,
        ]);
    }

    private function repository(Project $project): ?RepositoryConnection
    {
        return RepositoryConnection::query()
            ->where('project_id', $project->id)
            ->whereHas('connection.instance', fn ($q) => $q
                ->where('provider', 'github')->where('instance_key', GitHubInstance::key())
                ->where('base_url', GitHubInstance::BASE_URL))
            ->with('connection.instance')->first();
    }

    public function index(Request $request, Project $project, GitHubAppClient $github): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        $connected = $this->repository($project);
        $recentCommits = $connected ? $connected->commits()
            ->orderByDesc('committed_at')->orderByDesc('id')->limit(15)->get() : collect();
        // Show only a small provider-neutral snapshot. Reviews are counted without draft/PENDING items.
        $recentPulls = $connected ? $connected->changeRequests()
            ->withCount(['reviews as submitted_reviews_count' => fn ($query) => $query
                ->where('state', '!=', RepositoryChangeRequestReview::STATE_PENDING)])
            ->orderByDesc('remote_updated_at')->orderByDesc('id')->limit(15)->get() : collect();
        $pullParticipants = $connected && $recentPulls->isNotEmpty()
            ? app(RepositoryChangeRequestParticipantResolver::class)->resolve($connected, $recentPulls)
            : [];
        $ids = $recentCommits->pluck('author_external_user_id')->filter()->unique()->all();
        $linkedAuthors = $connected && $ids !== [] ? UserVcsAccount::query()
            ->where('vcs_instance_id', $connected->connection->vcs_instance_id)
            ->whereIn('external_user_id', $ids)
            ->whereHas('user', function ($query) use ($project): void {
                $query->whereNull('disabled_at')->where(function ($eligible) use ($project): void {
                    $eligible->whereHas('projectMemberships', fn ($membership) => $membership->where('project_id', $project->id))
                        ->orWhereHas('workspaceMemberships', fn ($membership) => $membership
                            ->where('workspace_id', $project->workspace_id)
                            ->whereNull('suspended_at')
                            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]));
                });
            })->with('user:id,name')->get()->keyBy('external_user_id') : collect();

        return Inertia::render('projects/integrations/GitHub', [
            'project' => [
                'id' => $project->id, 'name' => $project->name,
                'is_archived' => $project->archived_at !== null,
            ],
            'configured' => $github->configured(),
            'can_connect' => $request->user()->can('manageManagers', $project),
            'repository' => $connected ? [
                'full_name' => $connected->full_name,
                'default_branch' => $connected->default_branch,
                'installation_id' => (int) $connected->connection->external_connection_id,
                'account_login' => $connected->connection->account_login,
                'start_sha' => $connected->start_sha,
                'last_synced_sha' => $connected->last_synced_sha,
                'connected_at' => $connected->connected_at?->toIso8601String(),
                'last_synced_at' => $connected->last_synced_at?->toIso8601String(),
            ] : null,
            'commits' => $recentCommits->map(function (RepositoryCommit $commit) use ($linkedAuthors): array {
                $matched = $commit->author_external_user_id !== null
                    ? $linkedAuthors->get($commit->author_external_user_id) : null;
                return [
                    'sha' => $commit->sha,
                    'subject' => explode("\n", $commit->message, 2)[0],
                    'author' => $commit->author_name ?? $commit->author_login,
                    'taskflow_author' => $matched?->user ? [
                        'id' => $matched->user->id,
                        'name' => $matched->user->name,
                    ] : null,
                    'committed_at' => $commit->committed_at?->toIso8601String(),
                ];
            })->values(),
            'initial_tab' => $request->query('tab') === 'pulls' ? 'pulls' : 'commits',
            'pull_requests_count' => $connected ? $connected->changeRequests()->count() : 0,
            'pull_requests' => $recentPulls->map(function (RepositoryChangeRequest $pull) use ($connected, $pullParticipants): array {
                return [
                    'number' => $pull->number,
                    'title' => $pull->title,
                    // Do not expose untrusted stored URLs or PR bodies to the browser.
                    'web_url' => 'https://github.com/'.$connected->full_name.'/pull/'.$pull->number,
                    'state' => $pull->state,
                    'is_draft' => $pull->is_draft,
                    'author_login' => $pull->author_login,
                    'taskflow_author' => $pullParticipants[$pull->id]['author'] ?? null,
                    'source_branch' => $pull->source_branch,
                    'target_branch' => $pull->target_branch,
                    'reviews_count' => (int) $pull->submitted_reviews_count,
                    'remote_updated_at' => $pull->remote_updated_at?->toIso8601String(),
                ];
            })->values(),
            'legacy_authors_pending' => $connected && $request->user()->can('manageManagers', $project)
                ? $connected->commits()->whereNull('author_identity_checked_at')->count() : 0,
            'commits_count' => $connected ? $connected->commits()->count() : 0,
            'sync_runs' => $connected ? $connected->syncRuns()->orderByDesc('id')->limit(5)->get()
                ->map(fn ($run): array => [
                    'id' => $run->id, 'status' => $run->status,
                    'imported_count' => $run->imported_count,
                    'fetched_count' => $run->fetched_count,
                    'error_message' => $run->error_message,
                    'started_at' => $run->started_at?->toIso8601String(),
                ])->values() : [],
            'installations' => $request->user()->can('manageManagers', $project)
                ? VcsConnection::query()->where('workspace_id', $project->workspace_id)
                    ->whereHas('instance', fn ($q) => $q->where('provider', 'github')
                        ->where('instance_key', GitHubInstance::key())->where('base_url', GitHubInstance::BASE_URL))
                    ->orderBy('account_login')->get(['external_connection_id', 'account_login'])
                    ->map(fn ($item): array => [
                        'installation_id' => (int) $item->external_connection_id,
                        'account_login' => $item->account_login,
                    ])->values() : [],
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
        if (RepositoryConnection::query()->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['repository' => 'A repository is already linked. Disconnect it first.']);
        }

        $instance = GitHubInstance::get();
        $existing = VcsConnection::query()->where('vcs_instance_id', $instance->id)
            ->where('external_connection_id', (string) $installationId)->first();
        if ($existing && $existing->workspace_id !== $project->workspace_id) {
            throw ValidationException::withMessages(['installation_id' => 'This installation is already linked to another workspace.']);
        }
        if ($existing === null && ! $request->user()->is_system_admin) {
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
            $this->logProviderFailure($project, 'connect', $exception);
            throw ValidationException::withMessages([
                'repository' => 'Cannot access this repository with the specified GitHub App installation.',
            ]);
        }

        $owner = explode('/', $repositoryData['full_name'], 2)[0];
        if (! hash_equals(strtolower($installationData['account_login']), strtolower($owner))) {
            throw ValidationException::withMessages(['repository' => 'Repository owner does not match the GitHub App installation.']);
        }
        try {
            DB::transaction(function () use ($project, $instance, $installationId, $installationData, $repositoryData): void {
                $connection = VcsConnection::query()->firstOrCreate(
                    ['vcs_instance_id' => $instance->id, 'external_connection_id' => (string) $installationId],
                    [
                        'workspace_id' => $project->workspace_id,
                        'external_account_id' => (string) $installationData['github_account_id'],
                        'account_login' => $installationData['account_login'],
                        'account_type' => $installationData['account_type'],
                        'connected_at' => now(),
                    ],
                );
                if ($connection->workspace_id !== $project->workspace_id) {
                    throw ValidationException::withMessages(['installation_id' => 'Installation belongs to another workspace.']);
                }
                RepositoryConnection::query()->create([
                    'project_id' => $project->id,
                    'vcs_connection_id' => $connection->id,
                    'external_repository_id' => (string) $repositoryData['github_repository_id'],
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

    public function sync(Request $request, Project $project, RepositorySynchronizer $synchronizer): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        $repository = $this->repository($project) ?? abort(404);
        try {
            $result = $synchronizer->synchronize($repository);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->logProviderFailure($project, 'commits', $exception);
            throw ValidationException::withMessages([
                'sync' => app()->getLocale() === 'pl'
                    ? 'Synchronizacja nie powiodła się lub zmieniła się historia gałęzi. Zapisany SHA pozostał bez zmian. Sprawdź historię synchronizacji i spróbuj ponownie.'
                    : 'Synchronization failed or the branch history changed. The saved SHA was not advanced. Review the synchronization history and retry.',
            ]);
        }
        $count = (int) $result['imported'];
        // Enrich a small batch of already stored SHAs without delaying or rolling
        // back successful commit sync if GitHub's detail API is unavailable.
        $filesInfo = null;
        $fileSyncFailed = false;
        try {
            $filesInfo = app(RepositoryCommitFileSynchronizer::class)->synchronize($repository);
        } catch (\Throwable $exception) {
            $fileSyncFailed = true;
            $this->logProviderFailure($project, 'commit_files', $exception);
        }
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => app()->getLocale() === 'pl'
                ? (($count > 0 ? 'Synchronizacja GitHub zakończona. Nowe commity: '.$count.'.' : 'Synchronizacja GitHub zakończona. Brak nowych commitów.')
                    .($fileSyncFailed ? ' Nie udało się pobrać metadanych plików; spróbuj vcs:sync-commit-files.'
                        : ($filesInfo !== null && $filesInfo['remaining'] > 0 ? ' Pozostałe metadane: vcs:sync-commit-files.' : '')))
                : (($count > 0 ? 'Imported '.$count.' new GitHub commits.' : 'GitHub synchronization complete. No new commits.')
                    .($fileSyncFailed ? ' Could not sync commit file metadata; retry with vcs:sync-commit-files.'
                        : ($filesInfo !== null && $filesInfo['remaining'] > 0 ? ' Remaining metadata: vcs:sync-commit-files.' : ''))),
        ]);
        return to_route('projects.github.index', $project);
    }

    /** Synchronize PR metadata without advancing the commit cursor. */
    public function syncPulls(Request $request, Project $project, RepositoryChangeRequestSynchronizer $synchronizer): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        abort_if($project->archived_at !== null, 403);
        $repository = $this->repository($project) ?? abort(404);
        try {
            $result = $synchronizer->synchronize($repository);
        } catch (\Throwable $exception) {
            $this->logProviderFailure($project, 'pull_requests', $exception);
            throw ValidationException::withMessages([
                'pulls' => app()->getLocale() === 'pl'
                    ? 'Nie udało się zsynchronizować Pull Requestów. Sprawdź uprawnienia GitHub App lub spróbuj ponownie.'
                    : 'Could not sync pull requests. Check GitHub App permissions and try again.',
            ]);
        }
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => app()->getLocale() === 'pl'
                ? "Synchronizacja PR zakończona. Pobrano: {$result['fetched']}, nowych: {$result['created']}, zaktualizowanych: {$result['updated']}."
                : "PR sync complete. Fetched: {$result['fetched']}, created: {$result['created']}, updated: {$result['updated']}.",
        ]);
        return to_route('projects.github.index', ['project' => $project, 'tab' => 'pulls']);
    }

    /** Refresh a bounded batch of stored PR review histories. */
    public function syncReviews(Request $request, Project $project, RepositoryChangeRequestReviewSynchronizer $synchronizer): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        abort_if($project->archived_at !== null, 403);
        $repository = $this->repository($project) ?? abort(404);
        try {
            // This button explicitly requests a fresh check, even within the regular TTL.
            $result = $synchronizer->synchronize($repository, force: true);
        } catch (\Throwable $exception) {
            $this->logProviderFailure($project, 'reviews', $exception);
            throw ValidationException::withMessages([
                'reviews' => app()->getLocale() === 'pl'
                    ? 'Nie udało się odświeżyć recenzji. Spróbuj ponownie później.'
                    : 'Could not refresh reviews. Please try again.',
            ]);
        }
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => app()->getLocale() === 'pl'
                ? "Sprawdzono {$result['checked']} PR-ów. Recenzje: {$result['fetched']}, nowych: {$result['created']}, zmienionych: {$result['updated']}."
                : "Checked {$result['checked']} PRs. Reviews: {$result['fetched']}, created: {$result['created']}, updated: {$result['updated']}.",
        ]);
        return to_route('projects.github.index', ['project' => $project, 'tab' => 'pulls']);
    }

    public function backfillAuthors(Request $request, Project $project, RepositoryAuthorBackfiller $backfiller): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        $repository = $this->repository($project) ?? abort(404);
        try {
            $result = $backfiller->backfill($repository);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->logProviderFailure($project, 'authors', $exception);
            throw ValidationException::withMessages([
                'authors' => app()->getLocale() === 'pl'
                    ? 'Nie udało się uzupełnić autorów. Spróbuj ponownie później.'
                    : 'Could not refresh commit authors. Please try again later.',
            ]);
        }
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => app()->getLocale() === 'pl'
                ? 'Sprawdzono autorów '.$result['checked'].' commitów. Pozostało: '.$result['remaining'].'.'
                : 'Checked authors for '.$result['checked'].' commits. Remaining: '.$result['remaining'].'.',
        ]);
        return to_route('projects.github.index', $project);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        $repository = $this->repository($project);
        if ($repository !== null) {
            // A concurrent sync must not lose its run or imported commits halfway through.
            $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
            if (! $lock->get()) {
                throw ValidationException::withMessages([
                    'repository' => app()->getLocale() === 'pl'
                        ? 'Trwa synchronizacja repozytorium. Spróbuj odłączyć je po jej zakończeniu.'
                        : 'Repository synchronization is in progress. Disconnect after it finishes.',
                ]);
            }
            try {
                $repository->delete();
            } finally {
                $lock->release();
            }
        }
        // The workspace installation is shared with other projects and remains registered.
        return to_route('projects.github.index', $project);
    }
}
