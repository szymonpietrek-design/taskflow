<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Integrations\Vcs\Ai\GeneratePullRequestAiAnalysis;
use App\Integrations\Vcs\Ai\StalePullRequestAiEvidence;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/** Browser entry point: an explicitly approved, private analysis of one stored PR. */
final class ProjectPullRequestAiAnalysisController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        int $number,
        GeneratePullRequestAiAnalysis $analyze,
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize('manageManagers', $project);
        abort_if($project->archived_at !== null || $request->user()->disabled_at !== null, 403);

        $repository = RepositoryConnection::query()
            ->where('project_id', $project->id)
            ->whereHas('connection.instance', fn ($query) => $query
                ->where('provider', 'github')
                ->where('instance_key', GitHubInstance::key())
                ->where('base_url', GitHubInstance::BASE_URL))
            ->firstOrFail();
        $pull = $repository->changeRequests()->where('number', $number)->firstOrFail();

        $validated = $request->validate([
            'language' => ['required', 'string', 'max:35', 'regex:/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i'],
            'include_patches' => ['required', 'boolean'],
            'approve_external_sharing' => ['required', 'accepted'],
            'approve_code_sharing' => ['required', 'boolean'],
        ]);
        $patches = (bool) $validated['include_patches'];
        $codeConsent = (bool) $validated['approve_code_sharing'];
        if ($patches !== $codeConsent) {
            throw ValidationException::withMessages([
                'approve_code_sharing' => $this->message(
                    'Zgoda na udostępnienie fragmentów kodu musi odpowiadać wybranemu zakresowi analizy.',
                    'Code-sharing consent must match the selected analysis scope.',
                ),
            ]);
        }

        try {
            $analysis = $analyze->run(
                actor: $request->user(),
                project: $project,
                pull: $pull,
                language: strtolower($validated['language']),
                includePatches: $patches,
                approveExternalSharing: true,
                approveCodeSharing: $codeConsent,
            );
        } catch (StalePullRequestAiEvidence) {
            throw ValidationException::withMessages([
                'analysis' => $this->message(
                    'Zsynchronizuj ponownie PR i zmienione pliki przed analizą.',
                    'Sync the pull request and its changed files before analyzing.',
                ),
            ]);
        } catch (Throwable) {
            // Provider exceptions can include source code, prompts, token values or credentials.
            // Never log them or put their contents into validation/session responses.
            throw ValidationException::withMessages([
                'analysis' => $this->message(
                    'Nie udało się wygenerować analizy. Sprawdź aktualność danych i konfigurację AI.',
                    'Analysis could not be generated. Check data freshness and AI configuration.',
                ),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->message(
                "Analiza AI v{$analysis->version} została zapisana.",
                "AI analysis v{$analysis->version} has been saved.",
            ),
        ]);

        return to_route('projects.github.pull.show', [
            'project' => $project,
            'number' => $number,
        ]);
    }

    private function message(string $pl, string $en): string
    {
        return app()->getLocale() === 'pl' ? $pl : $en;
    }
}
