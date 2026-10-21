<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'installation_id',
    'github_account_id',
    'account_login',
    'account_type',
    'connected_at',
])]
class GitHubInstallation extends Model
{
    protected $table = 'github_installations';

    protected function casts(): array
    {
        return [
            'workspace_id' => 'integer',
            'installation_id' => 'integer',
            'github_account_id' => 'integer',
            'connected_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(GitHubRepository::class, 'github_installation_id');
    }
}
