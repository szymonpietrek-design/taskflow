<?php

namespace App\Integrations\Vcs\Ai;

use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Stage 4.1: creates versioned, pending analysis records without calling an AI provider.
 * Never persist source code, raw PR content, review text, API keys or prompt payloads here.
 */
final class PullRequestAiAnalysisHistory
{
    public function createPending(
        User $actor,
        Project $project,
        RepositoryChangeRequest $pull,
        string $language = 'en',
        bool $includePatchExcerpts = false,
    ): PullRequestAiAnalysis {
        Gate::forUser($actor)->authorize('manageManagers', $project);

        if ($project->archived_at !== null || $actor->disabled_at !== null) {
            throw new AuthorizationException('This project does not allow new AI analyses.');
        }

        if (! preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', $language) || strlen($language) > 35) {
            throw new InvalidArgumentException('Invalid analysis language.');
        }

        $repository = $this->repositoryFor($project, $pull);
        $sha = $pull->head_sha;
        if (! is_string($sha) || ! preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/i', $sha)) {
            throw new InvalidArgumentException('Synchronize a valid PR HEAD before requesting an analysis.');
        }

        // Record an explicit requested scope only; final consent and freshness are
        // rechecked at generation time in stage 4.2.
        if ($includePatchExcerpts && ($pull->files_synced_at === null || $pull->files_head_sha !== $sha)) {
            throw new InvalidArgumentException('Synchronize current PR files before requesting patch excerpts.');
        }

        $instance = $repository->connection->instance;
        $key = $this->sourceKey($repository, $pull);
        // Lock the project row to assign monotonic per-source versions atomically.
        return DB::transaction(function () use ($actor, $project, $pull, $repository, $instance, $key, $sha, $language, $includePatchExcerpts): PullRequestAiAnalysis {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $lastVersion = (int) PullRequestAiAnalysis::query()
                ->where('project_id', $project->id)
                ->where('source_key', $key)
                ->max('version');

            return PullRequestAiAnalysis::query()->create([
                'project_id' => $project->id,
                'repository_change_request_id' => $pull->id,
                'requested_by' => $actor->id,
                'source_key' => $key,
                'source_provider' => $instance->provider,
                'source_instance_key' => $instance->instance_key,
                'source_repository' => $repository->full_name,
                'source_pr_number' => $pull->number,
                'source_head_sha' => strtolower($sha),
                'version' => $lastVersion + 1,
                'language' => strtolower($language),
                'include_patch_excerpts' => $includePatchExcerpts,
                'status' => PullRequestAiAnalysis::STATUS_PENDING,
            ]);
        }, attempts: 3);
    }

    /** @return Collection<int, PullRequestAiAnalysis> */
    public function forPull(User $viewer, Project $project, RepositoryChangeRequest $pull, int $limit = 20): Collection
    {
        $this->authorizeView($viewer, $project);
        $repository = $this->repositoryFor($project, $pull);

        return PullRequestAiAnalysis::query()->where('project_id', $project->id)
            ->where('source_key', $this->sourceKey($repository, $pull))
            ->orderByDesc('version')->limit($this->boundedLimit($limit))->get();
    }

    /** Project history remains available after a repository is disconnected. */
    /** @return Collection<int, PullRequestAiAnalysis> */
    public function forProject(User $viewer, Project $project, int $limit = 20): Collection
    {
        $this->authorizeView($viewer, $project);

        return PullRequestAiAnalysis::query()->where('project_id', $project->id)
            ->orderByDesc('id')->limit($this->boundedLimit($limit))->get();
    }

    private function authorizeView(User $viewer, Project $project): void
    {
        Gate::forUser($viewer)->authorize('view', $project);
        if ($viewer->disabled_at !== null) {
            throw new AuthorizationException('Account disabled.');
        }
    }

    private function boundedLimit(int $limit): int
    {
        return max(1, min(100, $limit));
    }

    private function repositoryFor(Project $project, RepositoryChangeRequest $pull): RepositoryConnection
    {
        $repository = $pull->repository()->with('connection.instance')->first();
        if ($repository === null || (int) $repository->project_id !== (int) $project->id) {
            throw (new ModelNotFoundException())->setModel(RepositoryChangeRequest::class);
        }

        return $repository;
    }

    private function sourceKey(RepositoryConnection $repository, RepositoryChangeRequest $pull): string
    {
        $instance = $repository->connection->instance;

        return hash('sha256', json_encode([
            $instance->provider,
            $instance->instance_key,
            (string) $repository->external_repository_id,
            (string) $pull->external_change_request_id,
        ], JSON_THROW_ON_ERROR));
    }
}
