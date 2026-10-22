<?php

use App\Http\Controllers\Admin\SystemUserController;
use App\Http\Controllers\Admin\GitHubAppSettingsController;
use App\Http\Controllers\Admin\GitIntegrationsController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectGitHubController;
use App\Http\Controllers\Projects\ProjectGitHubPullRequestController;
use App\Http\Controllers\Projects\ProjectRepositoryIntegrationController;
use App\Http\Controllers\Projects\ProjectLogoController;
use App\Http\Controllers\Projects\ProjectMemberController;
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
use App\Http\Controllers\Tasks\TaskMarkdownPreviewController;
use App\Http\Controllers\Tasks\TaskMessageMarkdownPreviewController;
use App\Http\Controllers\Tasks\TaskFromMessageController;
use App\Http\Controllers\Tasks\TaskAttachmentController;
use App\Http\Controllers\Tasks\TaskInlineAttachmentController;
use App\Http\Controllers\Tasks\TaskMessageController;
use App\Http\Controllers\Tasks\TaskMessageAttachmentController;
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

// Internal application: never expose the Laravel starter welcome / registration landing page.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

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
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-system-users')->prefix('admin/users')->group(function () {
        Route::get('/', [SystemUserController::class, 'index'])->name('admin.users.index');
        Route::post('/', [SystemUserController::class, 'store'])->middleware('throttle:15,1')->name('admin.users.store');
        Route::patch('{user}', [SystemUserController::class, 'update'])->name('admin.users.update');
        Route::put('{user}/password', [SystemUserController::class, 'password'])->middleware('throttle:10,1')->name('admin.users.password');
        Route::patch('{user}/status', [SystemUserController::class, 'status'])->name('admin.users.status');
        Route::patch('{user}/role', [SystemUserController::class, 'role'])->name('admin.users.role');
    });

    Route::get('admin/integrations/git', GitIntegrationsController::class)
        ->middleware('can:manage-system-users')->name('admin.git.index');

    Route::middleware('can:manage-system-users')->prefix('admin/integrations/github')->group(function () {
        Route::get('/', [GitHubAppSettingsController::class, 'index'])->name('admin.github.index');
        Route::post('/', [GitHubAppSettingsController::class, 'store'])->middleware('throttle:6,1')->name('admin.github.store');
    });

    Route::get('worklogs', [WorklogController::class, 'index'])->name('worklogs.index');
    Route::get('worklogs/projects/{project}/tasks', [WorklogController::class, 'tasks'])->name('worklogs.tasks');
    Route::post('worklogs', [WorklogController::class, 'store'])->name('worklogs.store');
    Route::patch('worklogs/{worklog}', [WorklogController::class, 'update'])->name('worklogs.update');
    Route::delete('worklogs/{worklog}', [WorklogController::class, 'destroy'])->name('worklogs.destroy');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/integrations/repository', ProjectRepositoryIntegrationController::class)
        ->name('projects.repository.index');
    Route::get('projects/{project}/integrations/github', [ProjectGitHubController::class, 'index'])
        ->name('projects.github.index');
    Route::get('projects/{project}/integrations/github/pulls/{number}', ProjectGitHubPullRequestController::class)
        ->whereNumber('number')->name('projects.github.pull.show');
    Route::post('projects/{project}/integrations/github', [ProjectGitHubController::class, 'store'])
        ->middleware('throttle:10,1')->name('projects.github.store');
    Route::post('projects/{project}/integrations/github/pulls/{number}/files/sync', [ProjectGitHubPullRequestController::class, 'syncFiles'])
        ->whereNumber('number')->middleware('throttle:4,1')->name('projects.github.pull.files.sync');
    Route::post('projects/{project}/integrations/github/pulls/sync', [ProjectGitHubController::class, 'syncPulls'])
        ->middleware('throttle:6,1')->name('projects.github.pulls.sync');
    Route::post('projects/{project}/integrations/github/reviews/sync', [ProjectGitHubController::class, 'syncReviews'])
        ->middleware('throttle:6,1')->name('projects.github.reviews.sync');
    Route::post('projects/{project}/integrations/github/sync', [ProjectGitHubController::class, 'sync'])
        ->middleware('throttle:6,1')->name('projects.github.sync');
    Route::post('projects/{project}/integrations/github/authors', [ProjectGitHubController::class, 'backfillAuthors'])
        ->middleware('throttle:4,1')->name('projects.github.authors');
    Route::delete('projects/{project}/integrations/github', [ProjectGitHubController::class, 'destroy'])
        ->name('projects.github.destroy');
    Route::get('projects/{project}/members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
    Route::get('projects/{project}/members/candidates', [ProjectMemberController::class, 'candidates'])->middleware('throttle:60,1')->name('projects.members.candidates');
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->middleware('throttle:20,1')->name('projects.members.store');
    Route::patch('projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
    Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
    Route::get('projects/{project}/logo', [ProjectLogoController::class, 'show'])->name('projects.logo.show');
    Route::post('projects/{project}/logo', [ProjectLogoController::class, 'store'])->middleware('throttle:15,1')->name('projects.logo.store');
    Route::delete('projects/{project}/logo', [ProjectLogoController::class, 'destroy'])->name('projects.logo.destroy');

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
    Route::post('projects/{project}/tasks/{task}/description-preview', TaskMarkdownPreviewController::class)
        ->middleware('throttle:60,1')->name('tasks.description.preview');
    Route::patch('projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.status.update');

    Route::post('projects/{project}/tasks/{task}/attachments/inline', TaskInlineAttachmentController::class)
        ->middleware('throttle:30,1')->name('tasks.attachments.inline');
    Route::post('projects/{project}/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])
        ->middleware('throttle:30,1')->name('tasks.attachments.store');
    Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])
        ->name('tasks.attachments.download');
    Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/preview', [TaskAttachmentController::class, 'preview'])
        ->name('tasks.attachments.preview');
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])
        ->name('tasks.attachments.destroy');

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

    Route::post('projects/{project}/tasks/{task}/messages/preview', [TaskMessageMarkdownPreviewController::class, 'create'])
        ->middleware('throttle:60,1')->name('tasks.messages.preview');
    Route::post('projects/{project}/tasks/{task}/messages/{message}/preview', [TaskMessageMarkdownPreviewController::class, 'edit'])
        ->middleware('throttle:60,1')->name('tasks.messages.edit-preview');
    Route::post('projects/{project}/tasks/{task}/messages', [TaskMessageController::class, 'store'])
        ->name('tasks.messages.store');
    Route::patch('projects/{project}/tasks/{task}/messages/{message}', [TaskMessageController::class, 'update'])
        ->name('tasks.messages.update');
    Route::delete('projects/{project}/tasks/{task}/messages/{message}', [TaskMessageController::class, 'destroy'])
        ->name('tasks.messages.destroy');
    Route::delete('projects/{project}/tasks/{task}/messages/{message}/attachments/{attachment}', [TaskMessageAttachmentController::class, 'destroy'])
        ->name('tasks.messages.attachments.destroy');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces/{workspace}/projects', [WorkspaceProjectController::class, 'store'])->name('workspaces.projects.store');
    Route::patch('workspaces/{workspace}/projects/{project}', [WorkspaceProjectController::class, 'update'])->name('workspaces.projects.update');
    Route::patch('workspaces/{workspace}/projects/{project}/archive', [WorkspaceProjectController::class, 'archive'])->name('workspaces.projects.archive');
    Route::patch('workspaces/{workspace}/projects/{project}/restore', [WorkspaceProjectController::class, 'restore'])->name('workspaces.projects.restore');
    Route::get('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members.index');
    Route::get('workspaces/{workspace}/members/candidates', [WorkspaceMemberController::class, 'candidates'])
        ->middleware('throttle:60,1')->name('workspaces.members.candidates');
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
