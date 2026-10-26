<?php

namespace App\Support\Reports;

use App\Support\I18n\LanguageCatalog;

/** Translation strings for a saved report; never use the viewing user's UI locale. */
final class ReportDocumentLocale
{
    public function __construct(private readonly LanguageCatalog $catalog) {}

    public function text(string $locale, string $key, string $fallback): string
    {
        $locale = str_replace('_', '-', $locale);
        $base = strtolower(explode('-', $locale)[0]);

        foreach (array_unique([$locale, $base]) as $candidate) {
            $value = $this->catalog->read($candidate)[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}
