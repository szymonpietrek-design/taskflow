<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provider-neutral VCS data. GitHub remains on its original models in 3A.5.1. */
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
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RepositoryConnection::class, 'repository_connection_id');
    }
}
