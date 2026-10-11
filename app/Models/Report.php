<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $created_by
 * @property string $title
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property array<int, string> $sources
 * @property string|null $body
 * @property string|null $generated_locale
 * @property Carbon|null $generated_at
 */
#[Fillable(['project_id', 'created_by', 'title', 'period_start', 'period_end', 'sources', 'body', 'generated_locale', 'generated_at'])]
class Report extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'sources' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
