<?php

namespace App\Contracts\Ai;

interface ReportTextGenerator
{
    /** Generate a Markdown report based only on the selected project data. */
    public function generate(string $context, string $language): string;
}
