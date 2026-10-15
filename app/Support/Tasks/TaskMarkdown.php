<?php

namespace App\Support\Tasks;

use Illuminate\Support\Str;

class TaskMarkdown
{
    public function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        // User supplied Markdown may contain raw HTML or unsafe URLs.
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
