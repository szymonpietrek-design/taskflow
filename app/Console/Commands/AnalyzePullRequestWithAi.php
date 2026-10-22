<?php

namespace App\Console\Commands;

use App\Integrations\Vcs\Ai\GeneratePullRequestAiAnalysis;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

/** Explicitly authorized single PR analysis. No secrets, prompts or source code printed. */
final class AnalyzePullRequestWithAi extends Command
{
    protected $signature = 'vcs:analyze-pr
        {project : TaskFlow project ID}
        {number : PR number within that repository}
        {--user= : ID of the TaskFlow user authorizing this action}
        {--language=en : Output language code}
        {--patches : Include screened code patches in external AI request}
        {--approve-external-sharing : I explicitly approve sending PR metadata to DeepSeek}
        {--approve-code-sharing : I separately approve sending screened code patches to DeepSeek}';

    protected $description = 'Generate one versioned DeepSeek PR analysis after explicit data-sharing approval';

    public function handle(GeneratePullRequestAiAnalysis $service): int
    {
        if (! $this->option('approve-external-sharing')
            || ($this->option('patches') !== $this->option('approve-code-sharing'))) {
            $this->error('Explicit external sharing approval is required; --patches additionally requires --approve-code-sharing.');
            return self::FAILURE;
        }
        if (! ctype_digit((string) $this->argument('project'))
            || ! ctype_digit((string) $this->argument('number'))
            || ! ctype_digit((string) $this->option('user'))
            || (int) $this->argument('project') < 1 || (int) $this->argument('number') < 1
            || (int) $this->option('user') < 1) {
            $this->error('A positive project ID, PR number and --user=ID are required.');
            return self::FAILURE;
        }

        try {
            $actor = User::query()->findOrFail((int) $this->option('user'));
            $project = Project::query()->findOrFail((int) $this->argument('project'));
            $repository = RepositoryConnection::query()->where('project_id', $project->id)->firstOrFail();
            $pull = RepositoryChangeRequest::query()
                ->where('repository_connection_id', $repository->id)
                ->where('number', (int) $this->argument('number'))->firstOrFail();

            $analysis = $service->run(
                actor: $actor, project: $project, pull: $pull,
                language: (string) $this->option('language'),
                includePatches: (bool) $this->option('patches'),
                approveExternalSharing: true,
                approveCodeSharing: (bool) $this->option('approve-code-sharing'),
            );
            $this->info('PR analysis completed. ID: '.$analysis->id.', version: '.$analysis->version.'.');
            $this->line('Result saved privately in pull_request_ai_analyses; no content printed.');
            return self::SUCCESS;
        } catch (Throwable) {
            // Authorization, provider and validation exceptions never print sensitive details.
            $this->error('PR analysis could not be completed. Verify access, synchronized PR files and AI configuration.');
            return self::FAILURE;
        }
    }
}
