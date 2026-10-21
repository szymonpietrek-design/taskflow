<?php

namespace App\Integrations\GitHub;

use App\Models\GitHubAppSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubUserIdentityClient
{
    /** @return array{github_user_id: int, github_login: string, avatar_url: ?string} */
    public function verifyAuthorizationCode(GitHubAppSetting $settings, string $code, string $redirectUri): array
    {
        // Never log the temporary code, credentials, access token or raw API response.
        $response = Http::asForm()->acceptJson()
            ->withHeaders(['User-Agent' => 'TaskFlow-GitHub-App'])
            ->timeout(12)->connectTimeout(5)
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->successful() || $response->json('error') !== null) {
            throw new RuntimeException('GitHub OAuth code exchange failed.');
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            throw new RuntimeException('GitHub did not provide a valid user token.');
        }

        $identity = Http::baseUrl('https://api.github.com')
            ->withToken($token)->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'TaskFlow-GitHub-App',
            ])
            ->timeout(12)->connectTimeout(5)->get('/user');

        if (! $identity->successful()) {
            throw new RuntimeException('Could not verify GitHub user identity.');
        }

        $id = $identity->json('id');
        $login = $identity->json('login');
        $type = $identity->json('type');
        if (! is_int($id) || $id < 1 || ! is_string($login)
            || ! preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9-]{0,98})\z/D', $login)
            || $type !== 'User') {
            throw new RuntimeException('GitHub returned invalid user identity data.');
        }

        $avatar = $identity->json('avatar_url');
        if (! is_string($avatar) || strlen($avatar) > 2048
            || ! filter_var($avatar, FILTER_VALIDATE_URL)
            || parse_url($avatar, PHP_URL_SCHEME) !== 'https'
            || parse_url($avatar, PHP_URL_HOST) !== 'avatars.githubusercontent.com') {
            $avatar = null;
        }

        return [
            'github_user_id' => $id,
            'github_login' => $login,
            'avatar_url' => $avatar,
        ];
    }
}
