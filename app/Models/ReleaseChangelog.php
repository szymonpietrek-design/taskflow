<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $release_id
 * @property string|null $draft_body
 * @property string|null $published_body
 * @property int|null $updated_by
 * @property int|null $published_by
 * @property Carbon|null $published_at
 */
#[Fillable(['release_id', 'draft_body', 'published_body', 'updated_by', 'published_by', 'published_at'])]
class ReleaseChangelog extends Model
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
