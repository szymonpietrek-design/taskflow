<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CurrentWorkspaceController extends Controller
{
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('view', $workspace);

        $request->session()->put('current_workspace_id', $workspace->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Workspace switched.'),
        ]);

        $redirectTo = $request->string('redirect_to')->toString();

        if ($redirectTo !== '' && str_starts_with($redirectTo, '/') && ! str_starts_with($redirectTo, '//')) {
            return redirect($redirectTo);
        }

        return back();
    }
}
