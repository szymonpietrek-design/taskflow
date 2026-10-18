<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectLogoController extends Controller
{
    public function show(Request $request, Project $project): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $project);
        abort_if(! $project->logo_path || ! Storage::disk('local')->exists($project->logo_path), 404);

        return Storage::disk('local')->response($project->logo_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', Rule::dimensions()->minWidth(32)->minHeight(32)->maxWidth(2048)->maxHeight(2048)],
        ]);

        $path = $validated['logo']->store("project-logos/{$project->id}", 'local');
        if ($path === false) {
            abort(500, 'Unable to store project logo.');
        }

        $previousPath = $project->logo_path;
        try {
            $project->forceFill(['logo_path' => $path])->save();
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $project);
        $previousPath = $project->logo_path;
        if ($previousPath) {
            $project->forceFill(['logo_path' => null])->save();
            Storage::disk('local')->delete($previousPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }
}
