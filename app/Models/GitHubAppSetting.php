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
#[Fillable(['id', 'app_id', 'private_key', 'client_id', 'client_secret', 'updated_by'])]
#[Hidden(['private_key', 'client_secret'])]
class GitHubAppSetting extends Model
{
    protected $table = 'github_app_settings';

    protected function casts(): array
    {
        return [
            'app_id' => 'integer',
            'private_key' => 'encrypted',
            'client_secret' => 'encrypted',
            'updated_by' => 'integer',
        ];
    }

    public function userAuthorizationConfigured(): bool
    {
        return is_string($this->client_id) && $this->client_id !== ''
            && is_string($this->client_secret) && $this->client_secret !== '';
    }

    public static function current(): ?self
    {
        return self::query()->find(1);
    }
}
