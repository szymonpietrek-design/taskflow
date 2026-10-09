<?php

namespace App\Contracts\Ai;

interface ChangelogTextGenerator
{
    /** Generate Markdown based solely on the supplied release data. */
    public function generate(string $context, string $language): string;
}
