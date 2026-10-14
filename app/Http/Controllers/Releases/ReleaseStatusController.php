<?php

namespace App\Http\Controllers\Releases;

use App\Actions\Releases\ChangeReleaseStatus;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Releases\ChangeReleaseStatusRequest;
use App\Models\Project;
use App\Models\Release;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class ReleaseStatusController extends Controller
{
    public function update(
        ChangeReleaseStatusRequest $request,
        Project $project,
        Release $release,
        ChangeReleaseStatus $changeReleaseStatus,
    ): RedirectResponse {
        abort_unless($project->type === ProjectType::APPLICATION, 404);
        abort_unless($release->project_id === $project->id, 404);

        $data = $request->validated();
        $target = ReleaseStatus::from((int) $data['status']);
        $plannedFor = $target === ReleaseStatus::PLANNED && ! empty($data['planned_for'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $data['planned_for'])
            : null;

        try {
            $changeReleaseStatus->handle($request->user(), $release, $target, $plannedFor);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => __($exception->getMessage())]);
        }

        $request->session()->put('current_workspace_id', $project->workspace_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Release status updated.')]);

        return to_route('releases.show', [$project, $release]);
    }
}
