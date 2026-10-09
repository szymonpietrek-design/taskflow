<?php

use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskMessageController;
use App\Http\Controllers\Workspaces\CurrentWorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('projects/{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.status.update');

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
