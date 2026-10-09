<?php

namespace App\Http\Controllers\Releases;

use App\Actions\Releases\GenerateReleaseChangelog;
use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Releases\GenerateReleaseChangelogRequest;
use App\Models\Project;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

final class GenerateReleaseChangelogController extends Controller
{
    public function __invoke(
        GenerateReleaseChangelogRequest $request,
        Project $project,
        Release $release,
        GenerateReleaseChangelog $generate,
    ): RedirectResponse {
        abort_unless($project->type === ProjectType::APPLICATION && $release->project_id === $project->id, 404);
        Gate::authorize('view', $project);
        Gate::authorize('update', $project);

        try {
            $language = $request->validated('language', str_replace('_', '-', app()->getLocale()));
            $generate->handle($request->user(), $release, $language);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ai_generation' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // Never return raw provider errors, credentials or task data to the browser.
            report($exception);
            throw ValidationException::withMessages([
                'ai_generation' => 'AI generation failed. Check the provider configuration or try again later.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI changelog draft generated. Review it before publishing.')]);

        return to_route('releases.show', [$project, $release]);
    }
}
