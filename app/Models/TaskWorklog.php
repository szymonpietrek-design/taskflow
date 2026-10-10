<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property Carbon $work_date
 * @property int $duration_minutes
 * @property string|null $description
 * @property bool $is_creative
 */
#[Fillable([
    'task_id', 'user_id', 'work_date', 'duration_minutes', 'description', 'is_creative',
])]
class TaskWorklog extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'duration_minutes' => 'integer',
            'is_creative' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
