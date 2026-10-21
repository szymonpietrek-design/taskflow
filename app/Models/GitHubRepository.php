<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stores only the tracking baseline in stage 1: no historical import or AI analysis.
 */
#[Fillable([
    'project_id',
    'github_installation_id',
    'github_repository_id',
    'full_name',
    'default_branch',
    'start_sha',
    'last_synced_sha',
    'connected_at',
    'last_synced_at',
])]
class GitHubRepository extends Model
{
    protected $table = 'github_repositories';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'github_installation_id' => 'integer',
            'github_repository_id' => 'integer',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(GitHubInstallation::class, 'github_installation_id');
    }

    public function commits(): HasMany
    {
        return $this->hasMany(GitHubCommit::class, 'github_repository_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(GitHubSyncRun::class, 'github_repository_id');
    }
}
