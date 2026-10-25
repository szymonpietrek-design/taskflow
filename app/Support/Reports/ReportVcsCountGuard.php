<?php

namespace App\Support\Reports;

use InvalidArgumentException;

/** Reject obviously inflated VCS record counts; do not silently rewrite AI prose. */
final class ReportVcsCountGuard
{
    public function assertPlausible(string $markdown, string $context, string $language): void
    {
        $data = json_decode($context, true);
        if (! is_array($data) || ! isset($data['vcs']['commit_count'])) {
            return;
        }
        // Conservative check: supports explicit Polish/English count expressions.
        // Other languages still receive exact counts and strict instructions.
        $isPolish = str_starts_with(strtolower($language), 'pl');
        $isEnglish = str_starts_with(strtolower($language), 'en');
        if (! $isPolish && ! $isEnglish) {
            return;
        }
        $patterns = $isPolish
            ? [
                '~(?<![\pL\pN])([0-9]{1,5})\s+commit(?:ów|y|ami|ach|em)?\b~iu',
                '~(?<![\pL\pN])([0-9]{1,5})\s+(?:PR(?:-ów|ów|y)?|pull request(?:ów|y)?)\b~iu',
            ]
            : [
                '~(?<![\pL\pN])([0-9]{1,5})\s+commits?\b~iu',
                '~(?<![\pL\pN])([0-9]{1,5})\s+(?:PRs?|pull requests?)\b~iu',
            ];
        foreach ($patterns as $index => $pattern) {
            $max = (int) ($index === 0 ? $data['vcs']['commit_count'] : ($data['vcs']['pull_request_count'] ?? 0));
            if (preg_match_all($pattern, $markdown, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $value) {
                if ((int) $value > $max) {
                    throw new InvalidArgumentException(__('AI reported more commits or pull requests than exist in the selected evidence. Regenerate the report.'));
                }
            }
        }
    }
}
