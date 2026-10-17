<?php

namespace App\Support\Attachments;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * One upload path for task library files and message-attached files.
 * Callers own the transaction and delete stored paths if it rolls back.
 */
class TaskAttachmentUpload
{
    public function __construct(private readonly PrivateAttachmentStorage $storage) {}

    public function store(Task $task, User $uploader, UploadedFile $file): TaskAttachment
    {
        $path = $this->storage->store($file, 'task-attachments/'.$task->id);

        try {
            $originalName = str_replace(['/', '\\'], '_', $file->getClientOriginalName());
            $originalName = preg_replace('/[\x00-\x1F\x7F]/', '_', $originalName) ?: 'file';

            return $task->attachments()->create([
                'uploaded_by' => $uploader->id,
                'original_name' => mb_substr($originalName, 0, 255),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            $this->storage->delete($path);
            throw $exception;
        }
    }
}
