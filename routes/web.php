<?php

use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Releases\GenerateReleaseChangelogController;
use App\Http\Controllers\Releases\ReleaseChangelogController;
use App\Http\Controllers\Releases\ReleaseController;
use App\Http\Controllers\Releases\ReleaseStatusController;
use App\Http\Controllers\Releases\ReleaseTaskController;
use App\Http\Controllers\Reports\GenerateReportController;
use App\Http\Controllers\Reports\PublicReportController;
use App\Http\Controllers\Reports\ReportContentController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportPdfController;
use App\Http\Controllers\Reports\ReportPublicationController;
use App\Http\Controllers\Reports\ReportSharingController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskFromMessageController;
use App\Http\Controllers\Tasks\TaskMessageController;
use App\Http\Controllers\Tasks\TaskStatusController;
use App\Http\Controllers\Tasks\TaskWorklogController;
use App\Http\Controllers\Worklogs\WorklogController;
use App\Http\Controllers\Workspaces\CurrentWorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceProjectController;
use App\Http\Controllers\Workspaces\WorkspaceMemberController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::inertia('/', 'Welcome')->name('home');

// Guests can choose their UI language before logging in; authenticated users keep their account preference.
Route::post('ui-locale', function (Request $request): RedirectResponse {
    $validated = $request->validate(['locale' => ['required', 'string', Rule::in(['en', 'pl'])]]);

    return back()->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365));
})->name('ui-locale.update');

// A public report requires a published document and an unguessable share token.
Route::get('r/{report}/{token}', PublicReportController::class)
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:60,1')
    ->name('reports.public');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('worklogs', [WorklogController::class, 'index'])->name('worklogs.index');
    Route::get('worklogs/projects/{project}/tasks', [WorklogController::class, 'tasks'])->name('worklogs.tasks');
    Route::post('worklogs', [WorklogController::class, 'store'])->name('worklogs.store');
    Route::patch('worklogs/{worklog}', [WorklogController::class, 'update'])->name('worklogs.update');
    Route::delete('worklogs/{worklog}', [WorklogController::class, 'destroy'])->name('worklogs.destroy');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('projects/{project}/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('projects/{project}/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('projects/{project}/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('projects/{project}/reports/{report}/generate', GenerateReportController::class)->name('reports.generate');
    Route::put('projects/{project}/reports/{report}/content', ReportContentController::class)->name('reports.content.update');
    Route::post('projects/{project}/reports/{report}/publish', [ReportPublicationController::class, 'publish'])->name('reports.publish');
    Route::post('projects/{project}/reports/{report}/unpublish', [ReportPublicationController::class, 'unpublish'])->name('reports.unpublish');
    Route::post('projects/{project}/reports/{report}/share', [ReportSharingController::class, 'store'])->name('reports.share.store');
    Route::delete('projects/{project}/reports/{report}/share', [ReportSharingController::class, 'destroy'])->name('reports.share.destroy');
    Route::get('projects/{project}/reports/{report}/pdf', ReportPdfController::class)->name('reports.pdf');
    Route::delete('projects/{project}/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    Route::get('projects/{project}/releases', [ReleaseController::class, 'index'])->name('releases.index');
    Route::post('projects/{project}/releases', [ReleaseController::class, 'store'])->name('releases.store');
    Route::get('projects/{project}/releases/{release}', [ReleaseController::class, 'show'])->name('releases.show');
    Route::patch('projects/{project}/releases/{release}/status', [ReleaseStatusController::class, 'update'])->name('releases.status.update');
    Route::put('projects/{project}/releases/{release}/changelog', [ReleaseChangelogController::class, 'update'])->name('releases.changelog.update');
    Route::post('projects/{project}/releases/{release}/changelog/generate', GenerateReleaseChangelogController::class)->name('releases.changelog.generate');
    Route::post('projects/{project}/releases/{release}/changelog/publish', [ReleaseChangelogController::class, 'publish'])->name('releases.changelog.publish');
    Route::post('projects/{project}/releases/{release}/tasks', [ReleaseTaskController::class, 'store'])->name('releases.tasks.store');
    Route::post('projects/{project}/releases/{release}/tasks/completed', [ReleaseTaskController::class, 'attachCompleted'])->name('releases.tasks.attach-completed');
    Route::delete('projects/{project}/releases/{release}/tasks/{task}', [ReleaseTaskController::class, 'destroy'])->name('releases.tasks.destroy');

    Route::get('projects/{project}/statuses', [TaskStatusController::class, 'index'])->name('projects.statuses.index');
    Route::post('projects/{project}/statuses', [TaskStatusController::class, 'store'])->name('projects.statuses.store');
    Route::patch('projects/{project}/statuses/{status}', [TaskStatusController::class, 'update'])->name('projects.statuses.update');
    Route::patch('projects/{project}/statuses/{status}/move', [TaskStatusController::class, 'move'])->name('projects.statuses.move');
    Route::delete('projects/{project}/statuses/{status}', [TaskStatusController::class, 'destroy'])->name('projects.statuses.destroy');

    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('projects/{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.status.update');

    Route::post('projects/{project}/tasks/{task}/worklogs', [TaskWorklogController::class, 'store'])
        ->name('tasks.worklogs.store');
    Route::patch('projects/{project}/tasks/{task}/worklogs/{worklog}', [TaskWorklogController::class, 'update'])
        ->name('tasks.worklogs.update');
    Route::delete('projects/{project}/tasks/{task}/worklogs/{worklog}', [TaskWorklogController::class, 'destroy'])
        ->name('tasks.worklogs.destroy');

    Route::get('projects/{project}/tasks/{task}/messages/{message}/create-task', [TaskFromMessageController::class, 'create'])
        ->name('tasks.messages.create-task');
    Route::post('projects/{project}/tasks/{task}/messages/{message}/create-task', [TaskFromMessageController::class, 'store'])
        ->name('tasks.messages.store-task');

    Route::post('projects/{project}/tasks/{task}/messages', [TaskMessageController::class, 'store'])
        ->name('tasks.messages.store');
    Route::patch('projects/{project}/tasks/{task}/messages/{message}', [TaskMessageController::class, 'update'])
        ->name('tasks.messages.update');
    Route::delete('projects/{project}/tasks/{task}/messages/{message}', [TaskMessageController::class, 'destroy'])
        ->name('tasks.messages.destroy');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces/{workspace}/projects', [WorkspaceProjectController::class, 'store'])->name('workspaces.projects.store');
    Route::patch('workspaces/{workspace}/projects/{project}', [WorkspaceProjectController::class, 'update'])->name('workspaces.projects.update');
    Route::get('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members.index');
    Route::post('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])
        ->middleware('throttle:20,1')->name('workspaces.members.store');
    Route::patch('workspaces/{workspace}/members/{member}/suspend', [WorkspaceMemberController::class, 'suspend'])
        ->middleware('throttle:20,1')->name('workspaces.members.suspend');
    Route::patch('workspaces/{workspace}/members/{member}/restore', [WorkspaceMemberController::class, 'restore'])
        ->middleware('throttle:20,1')->name('workspaces.members.restore');
    Route::delete('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])
        ->middleware('throttle:20,1')->name('workspaces.members.destroy');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::put('workspaces/{workspace}/current', [CurrentWorkspaceController::class, 'update'])
        ->name('workspaces.current.update');
});

require __DIR__.'/settings.php';
