<?php

namespace Tests\Unit\Integrations\Vcs;

use App\Integrations\Vcs\Ai\PullRequestAiPatchPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PullRequestAiPatchPolicyTest extends TestCase
{
    private PullRequestAiPatchPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PullRequestAiPatchPolicy();
    }

    public function test_allows_bounded_nonsensitive_source_code_patch(): void
    {
        $this->assertNull($this->policy->inspect('src/OrderService.php', "@@ -1 +1 @@\n-old\n+new", false));
    }

    #[DataProvider('blockedPaths')]
    public function test_sensitive_paths_are_not_eligible(string $path): void
    {
        $this->assertSame('sensitive_path', $this->policy->inspect($path, '@@ -1 +1 @@', false));
    }

    public static function blockedPaths(): iterable
    {
        yield ['.env'];
        yield ['.env.production'];
        yield ['config/auth-secrets.php'];
        yield ['../../src/test.php'];
        yield ['vendor/package/script.php'];
        yield ['C:\\private\\file.php'];
        yield ['keys/app.pem'];
        yield ['storage/logs/laravel.log'];
    }

    public function test_does_not_allow_unexpected_binary_or_large_patches(): void
    {
        $this->assertSame('unsupported_file_type', $this->policy->inspect('logo.png', '@@ patch', false));
        $this->assertSame('patch_unavailable', $this->policy->inspect('main.go', null, false));
        $this->assertSame('patch_unavailable', $this->policy->inspect('main.go', '@@', true));
        $this->assertSame('patch_too_large', $this->policy->inspect('main.go', str_repeat('x', 4097), false));
        $this->assertSame('invalid_encoding', $this->policy->inspect('main.go', "\x00", false));
        $this->assertSame('invalid_encoding', $this->policy->inspect('main.go', "\x00\n", false));
        $this->assertSame('invalid_encoding', $this->policy->inspect('main.go', "\xFF", false));
        $this->assertSame('patch_unavailable', $this->policy->inspect('main.go', " \t\n", false));
    }

    #[DataProvider('suspiciousPatches')]
    public function test_drops_whole_patch_when_sensitive_pattern_is_present(string $patch): void
    {
        $this->assertSame('possible_secret', $this->policy->inspect('src/config.ts', $patch, false));
    }

    public static function suspiciousPatches(): iterable
    {
        yield ['+ authorization: Bearer abc'];
        yield ['+ password = "something"'];
        yield ['+ secret = "abcd"'];
        yield ['+ -----BEGIN RSA PRIVATE KEY-----'];
        yield ['+ github_pat_testinglongtoken123'];
    }
}
