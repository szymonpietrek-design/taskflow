<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['github_repository_id', 'sha', 'message', 'author_name', 'author_login', 'committed_at'])]
class GitHubCommit extends Model
{
    protected $table = 'github_commits';

    protected function casts(): array
    {
        return ['committed_at' => 'datetime'];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'github_repository_id');
    }
}
