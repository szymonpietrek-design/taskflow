<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** File-level PR/MR snapshot, scoped to one connected repository's change request. */
#[Fillable([
    'repository_change_request_id', 'path', 'path_hash', 'previous_path', 'status',
    'additions', 'deletions', 'changes', 'blob_sha', 'patch', 'patch_truncated',
])]
class RepositoryChangeRequestFile extends Model
{
    protected $table = 'repository_change_request_files';

    protected function casts(): array
    {
        return [
            'repository_change_request_id' => 'integer',
            'additions' => 'integer',
            'deletions' => 'integer',
            'changes' => 'integer',
            'patch_truncated' => 'boolean',
        ];
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(RepositoryChangeRequest::class, 'repository_change_request_id');
    }
}
