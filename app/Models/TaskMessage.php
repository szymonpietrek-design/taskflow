<?php

namespace App\Models;

use Database\Factories\TaskMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'task_id',
    'user_id',
    'body',
])]
class TaskMessage extends Model
{
    /** @use HasFactory<TaskMessageFactory> */
    use HasFactory;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Attachments live in the task library and may be reused by several messages. */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(TaskAttachment::class, 'task_message_attachments')
            ->orderBy('task_attachments.id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
