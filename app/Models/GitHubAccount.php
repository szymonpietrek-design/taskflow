<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verified identity only. OAuth access/refresh tokens are never persisted.
 */
#[Fillable(['user_id', 'github_user_id', 'github_login', 'avatar_url', 'connected_at'])]
class GitHubAccount extends Model
{
    protected $table = 'github_accounts';

    protected function casts(): array
    {
        return [
            'github_user_id' => 'integer',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
