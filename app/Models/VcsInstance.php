<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provider-neutral VCS data. GitHub remains on its original models in 3A.5.1. */
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
