<?php

namespace App\Integrations\Vcs\Ai;

use App\Contracts\Ai\PullRequestAnalysisGenerator;
use App\Models\Project;
use App\Models\PullRequestAiAnalysis;
use App\Models\RepositoryChangeRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Stage 4.2. Explicitly approved, append-only PR analysis runner (CLI or queued). */
final class GeneratePullRequestAiAnalysis
{
    public function __construct(
        private readonly PullRequestAiAnalysisHistory $history,
        private readonly PullRequestAiContextBuilder $builder,
        private readonly PullRequestAnalysisGenerator $generator,
        private readonly PullRequestAiUsage $usage,
    ) {}

    public function run(
        User $actor,
        Project $project,
        RepositoryChangeRequest $pull,
        string $language = 'en',
        bool $includePatches = false,
        bool $approveExternalSharing = false,
        bool $approveCodeSharing = false,
        ?PullRequestAiAnalysis $pendingAnalysis = null,
    ): PullRequestAiAnalysis {
        // Approval is always per invocation. Consent is not persisted as a reusable permission.
        if (! $approveExternalSharing) {
            throw new InvalidArgumentException('External AI data sharing needs explicit approval.');
        }
        if ($includePatches !== $approveCodeSharing) {
            throw new InvalidArgumentException('Patch sharing requires separate explicit approval.');
        }

        Gate::forUser($actor)->authorize('manageManagers', $project);
        if ($actor->disabled_at !== null || $project->archived_at !== null) {
            throw new AuthorizationException('New AI analyses are disabled for this account or project.');
        }
        if ((int) $pull->repository()->value('project_id') !== (int) $project->getKey()) {
            throw new InvalidArgumentException('The requested PR does not belong to the project.');
        }

        // Reuse the versioned identity and SHA verification created in stage 4.1.
        // Do not create an analysis entry for invalid consent or stale source data.
        $this->assertReady($pull);
        $rawEvidence = $this->builder->build($project, (int) $pull->number, $includePatches);
        $this->assertEvidence($rawEvidence, (string) $pull->head_sha, $includePatches);
        $evidence = $this->boundedExternalEvidence($rawEvidence);
        $json = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 50000) {
            throw new InvalidArgumentException('AI evidence exceeds the supported limit.');
        }

        if ($pendingAnalysis !== null) {
            // The job contains only an ID. Verify it cannot be reused for another user,
            // repository, language, revision, or patch-sharing scope.
            if ($pendingAnalysis->status !== PullRequestAiAnalysis::STATUS_PENDING
                || (int) $pendingAnalysis->requested_by !== (int) $actor->id
                || (int) $pendingAnalysis->project_id !== (int) $project->id
                || (int) $pendingAnalysis->repository_change_request_id !== (int) $pull->id
                || (bool) $pendingAnalysis->include_patch_excerpts !== $includePatches
                || $pendingAnalysis->language !== strtolower($language)
                || ! hash_equals(strtolower((string) $pendingAnalysis->source_head_sha), strtolower((string) $pull->head_sha))) {
                throw new StalePullRequestAiEvidence('The queued request no longer matches its approved source.');
            }
            $this->assertAnalysisSourceIsCurrent($pendingAnalysis);
        }
        // CLI remains supported; it must respect the same project limits as HTTP.
        // Count and allocation share a project row lock to prevent concurrent overbooking.
        $analysis = $pendingAnalysis ?? DB::transaction(function () use ($actor, $project, $pull, $language, $includePatches): PullRequestAiAnalysis {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            if (! $this->usage->quota($project)['allowed']) {
                throw new PullRequestAiQuotaExceeded('Project AI quota exceeded.');
            }
            return $this->history->createPending($actor, $project, $pull, $language, $includePatches);
        }, 3);
        $lock = Cache::lock('taskflow:pr-ai:'.(string) $analysis->source_key, 120);
        if (! $lock->get()) {
            // A second worker must not alter the row currently owned by the first.
            if ($pendingAnalysis === null) {
                $this->fail($analysis, 'already_running');
            }
            throw new RuntimeException('Another analysis for this PR is already running.');
        }

        try {
            $this->assertReady($pull->fresh());
            $this->assertAnalysisSourceIsCurrent($analysis);
            $claimed = PullRequestAiAnalysis::query()
                ->whereKey($analysis->id)
                ->where('status', PullRequestAiAnalysis::STATUS_PENDING)
                ->update([
                    'status' => PullRequestAiAnalysis::STATUS_RUNNING,
                    'started_at' => now(),
                    'input_digest' => hash('sha256', $json),
                ]);
            if ($claimed !== 1) {
                throw new RuntimeException('Analysis request has already been processed.');
            }

            // Only this call crosses the network boundary. Never log or persist $evidence/$json.
            $result = $this->generator->generate($evidence, $analysis->language);
            if ($result->markdown === '' || mb_strlen($result->markdown) > 12000
                || $result->provider !== 'deepseek'
                || $result->model === '' || strlen($result->model) > 120
                || preg_match('/<(?:script|iframe|object)\b|\bjavascript\s*:/i', $result->markdown)
                || ($result->promptTokens !== null && $result->promptTokens < 0)
                || ($result->completionTokens !== null && $result->completionTokens < 0)) {
                throw new RuntimeException('Invalid AI output.');
            }

            // Source may have changed during the external call. Do not publish stale output.
            $this->assertAnalysisSourceIsCurrent($analysis);
            $quote = $this->usage->quote($result->provider, $result->model, $result->promptTokens, $result->completionTokens);
            $updated = PullRequestAiAnalysis::query()
                ->whereKey($analysis->id)->where('status', PullRequestAiAnalysis::STATUS_RUNNING)
                ->update([
                    'status' => PullRequestAiAnalysis::STATUS_COMPLETED,
                    'result_markdown' => $result->markdown,
                    'ai_provider' => $result->provider,
                    'ai_model' => $result->model,
                    'prompt_tokens' => $result->promptTokens,
                    'completion_tokens' => $result->completionTokens,
                    'estimated_cost_usd' => $quote['cost_usd'] ?? null,
                    'input_price_per_million_usd' => $quote['input_rate'] ?? null,
                    'output_price_per_million_usd' => $quote['output_rate'] ?? null,
                    'error_code' => null,
                    'finished_at' => now(),
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('Analysis state changed unexpectedly.');
            }
            return $analysis->fresh();
        } catch (Throwable $error) {
            // Do not persist or print provider exceptions: they may contain fragments of input.
            $this->fail($analysis, $error instanceof StalePullRequestAiEvidence ? 'stale_evidence' : 'generation_failed');
            throw new RuntimeException('PR analysis failed. Check source freshness and AI configuration; no raw provider error is exposed.', 0);
        } finally {
            $lock->release();
        }
    }

    /** Validate freshness and the bounded disclosure scope before creating a queued request. */
    public function validateForQueue(Project $project, RepositoryChangeRequest $pull, bool $includePatches): void
    {
        $this->assertReady($pull);
        $raw = $this->builder->build($project, (int) $pull->number, $includePatches);
        $this->assertEvidence($raw, (string) $pull->head_sha, $includePatches);
        $json = json_encode($this->boundedExternalEvidence($raw), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 50000) {
            throw new InvalidArgumentException('AI evidence exceeds the supported limit.');
        }
    }

    private function assertReady(?RepositoryChangeRequest $pull): void
    {
        if ($pull === null || ! is_string($pull->head_sha)
            || ! preg_match('/\A[a-f0-9]{40}(?:[a-f0-9]{24})?\z/i', $pull->head_sha)
            || $pull->synced_at === null || $pull->synced_at->lt(now()->subMinutes(30))
            || $pull->files_synced_at === null || $pull->files_synced_at->lt(now()->subMinutes(30))
            || ! hash_equals(strtolower((string) $pull->head_sha), strtolower((string) $pull->files_head_sha))) {
            throw new StalePullRequestAiEvidence('Synchronize the PR and its files before generating analysis.');
        }
    }

    /** @param array<string, mixed> $evidence */
    private function assertEvidence(array $evidence, string $sha, bool $includePatches): void
    {
        if (($evidence['files_snapshot']['status'] ?? null) !== 'current'
            || ! hash_equals(strtolower($sha), strtolower((string) ($evidence['files_snapshot']['head_sha'] ?? '')))
            || ($evidence['files_omitted'] ?? true) || ($evidence['reviews_omitted'] ?? true)) {
            throw new StalePullRequestAiEvidence('AI context is incomplete or outdated.');
        }
        if ($includePatches && (int) ($evidence['patches_included'] ?? 0) < 1) {
            throw new InvalidArgumentException('No eligible patch excerpts; use metadata-only mode instead.');
        }
    }

    /** @param array<string, mixed> $raw @return array<string, mixed> */
    private function boundedExternalEvidence(array $raw): array
    {
        // An allowlist; NEVER send project IDs, member lists, user emails,
        // raw review comments, raw PR descriptions or unrelated repository commits.
        return [
            'schema' => 'taskflow.pr-ai-evidence.v1',
            'disclosure' => 'explicit_user_approval',
            'untrusted_input' => true,
            'pull_request' => $raw['pull_request'],
            'files_snapshot' => $raw['files_snapshot'],
            'files_count' => $raw['files_count'],
            'files' => $raw['files'],
            'reviews' => $raw['reviews'],
            'evidence_limits' => [
                'files_omitted' => $raw['files_omitted'],
                'reviews_omitted' => $raw['reviews_omitted'],
                'patches_included' => $raw['patches_included'],
                'patches_skipped_by_reason' => $raw['patches_skipped_by_reason'],
                'note' => 'No unrelated repository commits are included.',
            ],
        ];
    }

    private function assertAnalysisSourceIsCurrent(PullRequestAiAnalysis $analysis): void
    {
        $pull = $analysis->changeRequest()->first();
        if ($pull === null) {
            throw new StalePullRequestAiEvidence('PR was disconnected.');
        }
        $this->assertReady($pull);
        $repository = $pull->repository()->with('connection.instance')->first();
        if ($repository === null || $repository->connection?->instance === null) {
            throw new StalePullRequestAiEvidence('Repository source is unavailable.');
        }
        $instance = $repository->connection->instance;
        $sourceKey = hash('sha256', json_encode([
            $instance->provider,
            $instance->instance_key,
            (string) $repository->external_repository_id,
            (string) $pull->external_change_request_id,
        ], JSON_THROW_ON_ERROR));
        if (! hash_equals((string) $analysis->source_key, $sourceKey)
            || ! hash_equals(strtolower((string) $analysis->source_head_sha), strtolower((string) $pull->head_sha))
            || (int) $repository->project_id !== (int) $analysis->project_id
            || (int) $pull->number !== (int) $analysis->source_pr_number) {
            throw new StalePullRequestAiEvidence('PR source has changed.');
        }
    }

    private function fail(PullRequestAiAnalysis $analysis, string $code): void
    {
        DB::table('pull_request_ai_analyses')
            ->where('id', $analysis->id)
            ->whereIn('status', [PullRequestAiAnalysis::STATUS_PENDING, PullRequestAiAnalysis::STATUS_RUNNING])
            ->update([
                'status' => PullRequestAiAnalysis::STATUS_FAILED,
                'error_code' => $code,
                'result_markdown' => null,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
