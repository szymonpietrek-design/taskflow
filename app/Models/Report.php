<?php

namespace App\Models;

use App\Enums\ReportFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $created_by
 * @property ReportFormat $format
 * @property array<int,int>|null $contributor_ids
 * @property string $title
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property array<int, string> $sources
 * @property string|null $body
 * @property string|null $generated_locale
 * @property Carbon|null $generated_at
 */
#[Fillable(['project_id', 'created_by', 'format', 'contributor_ids', 'title', 'period_start', 'period_end', 'sources', 'body', 'generated_locale', 'generated_at'])]
class Report extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'sources' => 'array',
            'contributor_ids' => 'array',
            'format' => ReportFormat::class,
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
