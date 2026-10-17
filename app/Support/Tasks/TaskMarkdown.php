<?php

namespace App\Support\Tasks;

use Illuminate\Support\Str;

class TaskMarkdown
{
    public function render(?string $markdown, ?int $projectId = null, ?int $taskId = null, iterable $attachments = []): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        if ($projectId !== null && $taskId !== null) {
            // Share the guarded attachment resolver used by message Markdown.
            return (new TaskMessageMarkdown())->render($markdown, $projectId, $taskId, $attachments);
        }

        // User supplied Markdown may contain raw HTML or unsafe URLs.
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
