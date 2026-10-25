<?php

namespace App\Services\Ai;

use JsonException;
use RuntimeException;

/** Saves explicit local-only prompt snapshots OUTSIDE public storage and normal logs. */
final class ReportPromptArchive
{
    public function __construct(private readonly ?string $directory = null) {}

    /**
     * @throws JsonException
     */
    public function save(
        ?int $reportId,
        string $language,
        string $model,
        int $maxOutputTokens,
        string $systemPrompt,
        string $userPrompt,
    ): string {
        $directory = $this->directory ?? storage_path('app/private/ai-debug');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create private prompt directory.');
        }
        // Do not let other local users enumerate the captured report content.
        @chmod($directory, 0700);
        // Git exclusion, even if the installation does not ignore storage/app globally.
        $ignore = $directory.'/.gitignore';
        if (! is_file($ignore)) {
            file_put_contents($ignore, "*\n!.gitignore\n", LOCK_EX);
        }
        $createdAt = now();
        $payload = [
            'captured_at' => $createdAt->toIso8601String(),
            'report_id' => $reportId,
            'model' => $model,
            'language' => $language,
            'max_output_tokens' => $maxOutputTokens,
            'system_prompt_bytes' => strlen($systemPrompt),
            'user_prompt_bytes' => strlen($userPrompt),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
        $serialized = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $name = 'report-'.($reportId ?? 'live').'-'.$createdAt->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json';
        $path = $directory.'/'.$name;
        // 0600: owner-only read/write, regardless of ambient system umask.
        $previousUmask = umask(0077);
        try {
            if (file_put_contents($path, $serialized."\n", LOCK_EX) === false) {
                throw new RuntimeException('Unable to write prompt snapshot.');
            }
            chmod($path, 0600);
        } finally {
            umask($previousUmask);
        }

        return $path;
    }
}
