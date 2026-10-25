<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable-SHA, provider-neutral commit file metadata; never stores code or patches. */
#[Fillable([
    'repository_commit_id', 'path', 'path_hash', 'previous_path',
    'status', 'additions', 'deletions', 'changes',
])]
class RepositoryCommitFile extends Model
{
    protected function casts(): array
    {
        return [
            'repository_commit_id' => 'integer',
            'additions' => 'integer',
            'deletions' => 'integer',
            'changes' => 'integer',
        ];
    }

    public function commit(): BelongsTo
    {
        return $this->belongsTo(RepositoryCommit::class, 'repository_commit_id');
    }
}
