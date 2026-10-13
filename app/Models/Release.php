<?php

namespace App\Models;

use App\Enums\ReleaseStatus;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $created_by
 * @property string $version
 * @property int $version_major
 * @property int $version_minor
 * @property int $version_patch
 * @property string|null $name
 * @property string|null $description
 * @property ReleaseStatus $status
 * @property Carbon|null $planned_for
 * @property Carbon|null $released_at
 */
#[Fillable([
    'project_id',
    'created_by',
    'version',
    'version_major',
    'version_minor',
    'version_patch',
    'name',
    'description',
    'status',
    'planned_for',
    'released_at',
])]
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'version_major' => 'integer',
            'version_minor' => 'integer',
            'version_patch' => 'integer',
            'status' => ReleaseStatus::class,
            'planned_for' => 'date',
            'released_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function changelog(): HasOne
    {
        return $this->hasOne(ReleaseChangelog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
