<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A single server-wide configuration. The PEM is encrypted at rest with APP_KEY.
 * Never pass this model itself to Inertia, logs, or an API response.
 */
// This is a singleton (primary key = 1). Explicitly allow its ID so
// seeding and test fixtures do not silently create a different row.
#[Fillable(['id', 'app_id', 'private_key', 'updated_by'])]
#[Hidden(['private_key'])]
class GitHubAppSetting extends Model
{
    protected $table = 'github_app_settings';

    protected function casts(): array
    {
        return [
            'app_id' => 'integer',
            'private_key' => 'encrypted',
            'updated_by' => 'integer',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->find(1);
    }
}
