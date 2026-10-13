<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $release_id
 * @property int|null $parent_id
 * @property int|null $source_task_id
 * @property int|null $source_message_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property TaskType $type
 * @property TaskPriority $priority
 * @property int $status_id
 * @property int|null $assignee_id
 * @property int $created_by
 * @property int $position
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id',
    'release_id',
    'parent_id',
    'source_task_id',
    'source_message_id',
    'number',
    'title',
    'description',
    'type',
    'priority',
    'status_id',
    'assignee_id',
    'created_by',
    'position',
    'due_date',
    'completed_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return $this->project->key.'-'.$this->number;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function sourceTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_task_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(TaskMessage::class, 'source_message_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TaskMessage::class);
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(TaskWorklog::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }
}
