<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['github_repository_id', 'sha', 'message', 'author_name', 'author_login', 'author_github_user_id', 'author_identity_checked_at', 'committed_at'])]
class GitHubCommit extends Model
{
    protected $table = 'github_commits';

    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
            'author_github_user_id' => 'integer',
            'author_identity_checked_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'github_repository_id');
    }
}
