<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provider and server identity, shared by every VCS integration. */
#[Fillable([
    'provider',
    'base_url',
    'instance_key',
])]
class VcsInstance extends Model
{
    protected $table = 'vcs_instances';

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'cutover_at' => 'datetime',
        ];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(VcsConnection::class);
    }

    public function userAccounts(): HasMany
    {
        return $this->hasMany(UserVcsAccount::class);
    }
}
