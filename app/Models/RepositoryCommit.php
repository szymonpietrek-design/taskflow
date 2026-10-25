<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provider-neutral VCS data; GitHub now uses these shared tables. */
#[Fillable([
    'repository_connection_id',
    'sha',
    'message',
    'author_name',
    'author_login',
    'author_external_user_id',
    'author_identity_checked_at',
    'committed_at',
    'legacy_github_commit_id',
])]
class RepositoryCommit extends Model
{
    protected $table = 'repository_commits';

    protected function casts(): array
    {
        return [
            'repository_connection_id' => 'integer',
            'committed_at' => 'datetime',
            'author_identity_checked_at' => 'datetime',
            'legacy_github_commit_id' => 'integer',
            'files_synced_at' => 'datetime',
            'files_count' => 'integer',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(RepositoryCommitFile::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RepositoryConnection::class, 'repository_connection_id');
    }
}
