<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provider-neutral VCS data. GitHub remains on its original models in 3A.5.1. */
#[Fillable([
    'user_id',
    'vcs_instance_id',
    'external_user_id',
    'login',
    'avatar_url',
    'connected_at',
    'legacy_github_account_id',
])]
class UserVcsAccount extends Model
{
    protected $table = 'user_vcs_accounts';

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'vcs_instance_id' => 'integer',
            'connected_at' => 'datetime',
            'legacy_github_account_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(VcsInstance::class, 'vcs_instance_id');
    }
}
