<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provider-neutral VCS data; GitHub now uses these shared tables. */
#[Fillable([
    'project_id',
    'vcs_connection_id',
    'external_repository_id',
    'full_name',
    'default_branch',
    'start_sha',
    'last_synced_sha',
    'connected_at',
    'last_synced_at',
    'legacy_github_repository_id',
])]
class RepositoryConnection extends Model
{
    protected $table = 'repository_connections';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'vcs_connection_id' => 'integer',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'legacy_github_repository_id' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(VcsConnection::class, 'vcs_connection_id');
    }

    public function commits(): HasMany
    {
        return $this->hasMany(RepositoryCommit::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(RepositoryChangeRequest::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(RepositorySyncRun::class);
    }
}
