<?php

namespace App\Support\Attachments;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Shared private disk file operations; reusable by future comment attachments.
 */
class PrivateAttachmentStorage
{
    public const DISK = 'local';

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);

        if (! is_string($path)) {
            throw new RuntimeException('Could not store attachment.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
