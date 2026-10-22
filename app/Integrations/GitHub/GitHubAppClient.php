<?php

namespace App\Integrations\GitHub;

use App\Models\GitHubAppSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubAppClient
{
    public function configured(): bool
    {
        $settings = GitHubAppSetting::current();

        return $settings !== null
            && $settings->app_id > 0
            && is_string($settings->private_key)
            && $settings->private_key !== '';
    }

    /**
     * @return array{installation_id: int, github_account_id: int, account_login: string, account_type: string}
     */
    public function inspectInstallation(int $installationId): array
    {
        if ($installationId < 1 || ! $this->configured()) {
            throw new RuntimeException('GitHub App is not configured or installation ID is invalid.');
        }

        $installation = $this->api()->withToken($this->appJwt())
            ->get('/app/installations/'.$installationId)->throw()->json();

        $accountId = $installation['account']['id'] ?? null;
        $login = $installation['account']['login'] ?? null;
        $type = $installation['account']['type'] ?? null;

        if ((int) ($installation['id'] ?? 0) !== $installationId
            || ! is_numeric($accountId) || (int) $accountId < 1
            || ! is_string($login) || $login === ''
            || ! is_string($type) || ! in_array($type, ['User', 'Organization'], true)
            || ($installation['suspended_at'] ?? null) !== null) {
            throw new RuntimeException('GitHub returned invalid or suspended installation metadata.');
        }

        return [
            'installation_id' => $installationId,
            'github_account_id' => (int) $accountId,
            'account_login' => $login,
            'account_type' => $type,
        ];
    }

    /**
     * @return array{github_repository_id: int, full_name: string, default_branch: string, head_sha: string}
     */
    public function inspectRepository(int $installationId, string $fullName): array
    {
        if (! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $fullName)) {
            throw new RuntimeException('Invalid GitHub repository name.');
        }

        $token = $this->installationToken($installationId);
        $client = $this->api()->withToken($token);
        $repo = $client->get('/repos/'.$fullName)->throw()->json();
        $id = $repo['id'] ?? null;
        $canonical = $repo['full_name'] ?? null;
        $branch = $repo['default_branch'] ?? null;

        if (! is_numeric($id) || (int) $id < 1
            || ! is_string($canonical) || ! is_string($branch) || $branch === ''
            || ! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', $canonical)) {
            throw new RuntimeException('GitHub returned incomplete repository metadata.');
        }

        // Fetch just the current HEAD. Never list or import historical commits during connection.
        $commit = $client->get('/repos/'.$canonical.'/commits/'.rawurlencode($branch))->throw()->json();
        $sha = $commit['sha'] ?? null;
        if (! is_string($sha) || ! preg_match('/\A[a-fA-F0-9]{40}\z/D', $sha)) {
            throw new RuntimeException('GitHub returned an invalid branch HEAD.');
        }

        return [
            'github_repository_id' => (int) $id,
            'full_name' => $canonical,
            'default_branch' => $branch,
            'head_sha' => strtolower($sha),
        ];
    }

    protected function installationToken(int $installationId, array $permissions = ['contents' => 'read']): string
    {
        if (! $this->configured() || $installationId < 1) {
            throw new RuntimeException('GitHub App is not configured.');
        }

        $response = $this->api()->withToken($this->appJwt())
            ->post('/app/installations/'.$installationId.'/access_tokens', [
                'permissions' => $permissions,
            ])->throw()->json();

        if (! is_string($response['token'] ?? null) || $response['token'] === '') {
            throw new RuntimeException('GitHub did not issue an installation token.');
        }

        return $response['token'];
    }

    protected function appJwt(): string
    {
        $settings = GitHubAppSetting::current();
        if ($settings === null || $settings->app_id < 1 || ! is_string($settings->private_key)
            || $settings->private_key === '') {
            throw new RuntimeException('GitHub App has not been configured.');
        }

        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $now = time();
        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = $encode(['iat' => $now - 60, 'exp' => $now + 480, 'iss' => (string) $settings->app_id]);
        $message = $header.'.'.$payload;
        if (! openssl_sign($message, $signature, $settings->private_key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Cannot sign GitHub App JWT.');
        }

        return $message.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    protected function api(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'TaskFlow-GitHub-App',
            ])
            ->timeout(12)->connectTimeout(5);
    }

}
