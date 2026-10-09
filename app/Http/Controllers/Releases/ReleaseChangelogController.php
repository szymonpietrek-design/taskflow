<?php

namespace App\Http\Controllers\Releases;

use App\Actions\Releases\PublishReleaseChangelog;
use App\Actions\Releases\SaveReleaseChangelog;
use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Releases\SaveReleaseChangelogRequest;
use App\Models\Project;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class ReleaseChangelogController extends Controller
{
    public function update(
        SaveReleaseChangelogRequest $request,
        Project $project,
        Release $release,
        SaveReleaseChangelog $save,
    ): RedirectResponse {
        $this->checkRelease($project, $release);

        try {
            $save->handle($request->user(), $release, ($request->validated('draft_body') ?? ''));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['draft_body' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Changelog draft saved.')]);

        return to_route('releases.show', [$project, $release]);
    }

    public function publish(
        Request $request,
        Project $project,
        Release $release,
        PublishReleaseChangelog $publish,
    ): RedirectResponse {
        $this->checkRelease($project, $release);

        try {
            $publish->handle($request->user(), $release);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['changelog' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Changelog published.')]);

        return to_route('releases.show', [$project, $release]);
    }

    private function checkRelease(Project $project, Release $release): void
    {
        abort_unless($project->type === ProjectType::APPLICATION && $release->project_id === $project->id, 404);
        Gate::authorize('view', $project);
    }
}
