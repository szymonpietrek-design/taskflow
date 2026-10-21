<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provider-neutral VCS data. GitHub remains on its original models in 3A.5.1. */
#[Fillable([
    'repository_connection_id',
    'from_sha',
    'to_sha',
    'fetched_count',
    'imported_count',
    'status',
    'error_message',
    'started_at',
    'finished_at',
    'legacy_github_sync_run_id',
])]
class RepositorySyncRun extends Model
{
    protected $table = 'repository_sync_runs';

    protected function casts(): array
    {
        return [
            'repository_connection_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'legacy_github_sync_run_id' => 'integer',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RepositoryConnection::class, 'repository_connection_id');
    }
}
