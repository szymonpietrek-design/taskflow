<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $uploaded_by
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 */
#[Fillable(['task_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size'])]
class TaskAttachment extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }
}
