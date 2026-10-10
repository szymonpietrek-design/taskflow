<?php

use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Releases\ReleaseController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Releases\ReleaseTaskController;
use App\Http\Controllers\Releases\ReleaseStatusController;
use App\Http\Controllers\Releases\ReleaseChangelogController;
use App\Http\Controllers\Releases\GenerateReleaseChangelogController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskFromMessageController;
use App\Http\Controllers\Tasks\TaskMessageController;
use App\Http\Controllers\Tasks\TaskStatusController;
use App\Http\Controllers\Workspaces\CurrentWorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('projects/{project}/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('projects/{project}/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('projects/{project}/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
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
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::put('workspaces/{workspace}/current', [CurrentWorkspaceController::class, 'update'])
        ->name('workspaces.current.update');
});

require __DIR__.'/settings.php';
