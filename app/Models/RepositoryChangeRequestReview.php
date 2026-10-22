<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single immutable-ID review whose current state may change (e.g. DISMISSED). */
#[Fillable([
    'repository_change_request_id',
    'external_review_id',
    'reviewer_external_user_id',
    'reviewer_login',
    'state',
    'body',
    'web_url',
    'commit_sha',
    'submitted_at',
])]
class RepositoryChangeRequestReview extends Model
{
    protected $table = 'repository_change_request_reviews';

    public const STATE_APPROVED = 'approved';
    public const STATE_CHANGES_REQUESTED = 'changes_requested';
    public const STATE_COMMENTED = 'commented';
    public const STATE_DISMISSED = 'dismissed';
    public const STATE_PENDING = 'pending';

    protected function casts(): array
    {
        return [
            'repository_change_request_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(RepositoryChangeRequest::class, 'repository_change_request_id');
    }
}
