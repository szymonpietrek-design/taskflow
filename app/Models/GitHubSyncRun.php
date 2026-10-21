<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'github_repository_id', 'from_sha', 'to_sha', 'fetched_count', 'imported_count',
    'status', 'error_message', 'started_at', 'finished_at',
])]
class GitHubSyncRun extends Model
{
    protected $table = 'github_sync_runs';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'github_repository_id');
    }
}
