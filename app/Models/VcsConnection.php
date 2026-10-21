<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provider-neutral VCS data. GitHub remains on its original models in 3A.5.1. */
#[Fillable([
    'vcs_instance_id',
    'workspace_id',
    'external_connection_id',
    'external_account_id',
    'account_login',
    'account_type',
    'connected_at',
    'legacy_github_installation_id',
])]
class VcsConnection extends Model
{
    protected $table = 'vcs_connections';

    protected function casts(): array
    {
        return [
            'vcs_instance_id' => 'integer',
            'workspace_id' => 'integer',
            'connected_at' => 'datetime',
            'legacy_github_installation_id' => 'integer',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(VcsInstance::class, 'vcs_instance_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(RepositoryConnection::class);
    }
}
