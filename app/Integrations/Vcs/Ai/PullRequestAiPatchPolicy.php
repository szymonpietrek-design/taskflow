<?php

namespace App\Integrations\Vcs\Ai;

/**
 * Conservative local screening of untrusted Git diff text.
 * IMPORTANT: This is NOT a guarantee that arbitrary secrets are detected.
 * Any future external AI call requires a separate, explicit approval step.
 */
final class PullRequestAiPatchPolicy
{
    private const MAX_PATCH_BYTES = 4096;

    public function inspect(string $path, ?string $patch, bool $truncated): ?string
    {
        if ($truncated || $patch === null) {
            return 'patch_unavailable';
        }
        // trim() strips NUL bytes, so validate binary/encoding BEFORE checking emptiness.
        if (! mb_check_encoding($path, 'UTF-8') || ! mb_check_encoding($patch, 'UTF-8')
            || str_contains($path, "\0") || str_contains($patch, "\0")) {
            return 'invalid_encoding';
        }
        if (trim($patch) === '') {
            return 'patch_unavailable';
        }
        if (strlen($patch) > self::MAX_PATCH_BYTES) {
            return 'patch_too_large';
        }

        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', strtolower($normalized));
        $name = strtolower(basename($normalized));
        if ($path === '' || str_starts_with($normalized, '/')
            || preg_match('/^[a-z]:/i', $normalized)
            || array_intersect(['..', '.git', 'vendor', 'node_modules', 'storage', 'dist', 'build'], $segments) !== []
            || preg_match('/[\r\n]/', $normalized)
            || array_filter($segments, fn (string $segment): bool => str_starts_with($segment, '.') && $segment !== '.github') !== []
            || str_starts_with($name, '.') || preg_match('/(^|[._-])(secrets?|credentials?|private|tokens?|passwords?|keypair|keystore)([._-]|$)/i', $name)
            || preg_match('/\.(pem|key|p12|pfx|jks|keystore|sqlite|db|env|crt|cer|asc)$/i', $name)) {
            return 'sensitive_path';
        }
        if (! preg_match('/\.(php|js|jsx|ts|tsx|vue|go|py|rb|rs|java|kt|cs|sql|css|scss|html|md|yml|yaml|json|xml|sh|txt)$/i', $name)) {
            return 'unsupported_file_type';
        }

        // Conservative, whole-patch rejection rather than partial redaction of code.
        // The list deliberately produces false positives; it cannot discover all secrets.
        if (preg_match('/(?:-----BEGIN [^-]*(?:PRIVATE KEY|CERTIFICATE)-----|github_pat_|gh[pousr]_[A-Za-z0-9_]{12,}|\bAKIA[0-9A-Z]{16}\b|\bBearer\s+[^\s]+|\b(?:api[_-]?key|secret|access[_-]?token|client[_-]?secret|password|private[_-]?key|authorization)\b|\beyJ[A-Za-z0-9_-]{20,}\.eyJ[A-Za-z0-9_-]+)/i', $patch)) {
            return 'possible_secret';
        }
        return null;
    }
}
