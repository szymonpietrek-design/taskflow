<?php

namespace App\Integrations\Vcs\Providers;

use App\Models\VcsInstance;
use RuntimeException;

/** Fixed github.com identity. Never use user-supplied hostnames for GitHub App credentials. */
final class GitHubInstance
{
    public const BASE_URL = 'https://github.com';

    public static function key(): string
    {
        return hash('sha256', self::BASE_URL);
    }

    public static function get(): VcsInstance
    {
        $instance = VcsInstance::query()->firstOrCreate(
            ['provider' => 'github', 'instance_key' => self::key()],
            ['base_url' => self::BASE_URL],
        );
        if ($instance->base_url !== self::BASE_URL) {
            throw new RuntimeException('GitHub instance host does not match the trusted host.');
        }
        return $instance;
    }
}
