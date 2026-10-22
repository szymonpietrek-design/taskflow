<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provider-neutral PR / MR snapshot; no network access or author inference. */
#[Fillable([
    'repository_connection_id',
    'external_change_request_id',
    'number',
    'title',
    'body',
    'web_url',
    'state',
    'is_draft',
    'is_locked',
    'source_branch',
    'target_branch',
    'source_repository',
    'head_sha',
    'base_sha',
    'merge_commit_sha',
    'author_external_user_id',
    'author_login',
    'merged_by_external_user_id',
    'merged_by_login',
    'merged_by_checked_at',
    'remote_created_at',
    'remote_updated_at',
    'closed_at',
    'merged_at',
    'synced_at',
])]
class RepositoryChangeRequest extends Model
{
    protected $table = 'repository_change_requests';

    public const STATE_OPEN = 'open';
    public const STATE_CLOSED = 'closed';
    public const STATE_MERGED = 'merged';

    protected function casts(): array
    {
        return [
            'repository_connection_id' => 'integer',
            'number' => 'integer',
            'is_draft' => 'boolean',
            'is_locked' => 'boolean',
            'remote_created_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'closed_at' => 'datetime',
            'merged_at' => 'datetime',
            'merged_by_checked_at' => 'datetime',
            'reviews_checked_at' => 'datetime',
            'files_synced_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(RepositoryChangeRequestFile::class, 'repository_change_request_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(RepositoryChangeRequestReview::class, 'repository_change_request_id');
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RepositoryConnection::class, 'repository_connection_id');
    }
}
