<?php

namespace Tests\Feature\Projects;

use App\Integrations\GitHub\GitHubAppClient;
use App\Models\GitHubAppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubAppClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_encrypted_database_key_for_app_jwt_and_reads_only_repository_head(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $pem));
        GitHubAppSetting::query()->create(['id' => 1, 'app_id' => 1234, 'private_key' => $pem]);

        $head = str_repeat('c', 40);
        Http::fake([
            'https://api.github.com/app/installations/456/access_tokens' => Http::response(['token' => 'test-token'], 201),
            'https://api.github.com/app/installations/456' => Http::response([
                'id' => 456,
                'account' => ['id' => 78, 'login' => 'demo', 'type' => 'Organization'],
                'suspended_at' => null,
            ]),
            'https://api.github.com/repos/demo/backend/commits/main' => Http::response(['sha' => $head]),
            'https://api.github.com/repos/demo/backend' => Http::response([
                'id' => 987, 'full_name' => 'demo/backend', 'default_branch' => 'main',
            ]),
        ]);

        $client = app(GitHubAppClient::class);
        $installation = $client->inspectInstallation(456);
        $repository = $client->inspectRepository(456, 'demo/backend');
        $this->assertSame('demo', $installation['account_login']);
        $this->assertSame('demo/backend', $repository['full_name']);
        $this->assertSame($head, $repository['head_sha']);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/compare/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '?per_page='));
    }

    public function test_unconfigured_client_does_not_make_http_requests(): void
    {
        Http::fake();
        $this->assertFalse(app(GitHubAppClient::class)->configured());
        Http::assertNothingSent();
    }
}
