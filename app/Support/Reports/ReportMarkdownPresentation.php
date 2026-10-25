<?php

namespace App\Support\Reports;

use App\Enums\ReportFormat;
use Illuminate\Support\Str;

/** Safely render Markdown; additionally highlight unformatted technical identifiers. */
final class ReportMarkdownPresentation
{
    public function render(string $markdown, ReportFormat $format): string
    {
        return Str::markdown(
            $format === ReportFormat::TECHNICAL_REPORT
                ? $this->highlightIdentifiers($markdown) : $markdown,
            ['html_input' => 'strip', 'allow_unsafe_links' => false],
        );
    }

    private function highlightIdentifiers(string $markdown): string
    {
        // Never rewrite existing inline/fenced code, hyperlinks or URL targets.
        // Keep stored Markdown intact; both preview and exported documents use
        // this presentation layer, including older reports and manually edited ones.
        $parts = preg_split(
            '~(`{3,}[\s\S]*?`{3,}|`[^`\n]+`|\[[^]\n]+\]\([^\n)]+\)|https?://[^\s<>]+)~u',
            $markdown,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );
        if ($parts === false) {
            return $markdown;
        }

        foreach ($parts as $index => &$part) {
            if ($index % 2 !== 0) {
                continue;
            }
            $part = preg_replace_callback(
                '~(?<![\pL\pN_`/])(?:'
                .'(?:\.github|app|src|tests|database|resources|routes|config)/[A-Za-z0-9_./-]*[A-Za-z0-9_-]'
                .'|[A-Za-z_][A-Za-z0-9_.-]*\.(?:php|vue|tsx?|jsx?|yaml|yml|json|sql)'
                .'|(?:[A-Z][a-z0-9]+){2,}[A-Za-z0-9_]*'
                .')(?![\pL\pN_`])~u',
                static function (array $match): string {
                    $word = $match[0];
                    // Product and platform names are not PHP identifiers.
                    if (in_array($word, ['GitHub', 'GitLab', 'TaskFlow', 'DeepSeek', 'JavaScript', 'TypeScript', 'PullRequest', 'Markdown', 'MacOS', 'OAuth'], true)) {
                        return $word;
                    }
                    return '`'.$word.'`';
                },
                $part,
            ) ?? $part;
        }
        unset($part);

        return implode('', $parts);
    }
}
