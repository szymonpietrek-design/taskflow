<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Exceptions\PrismException;
use RuntimeException;

/** The report-only DeepSeek adapter; no changes to PR or changelog generation. */
final class ReportDeepSeekClient
{
    public function generate(
        string $baseUrl,
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        int $maxTokens,
        bool $thinking = false,
    ): string {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => $maxTokens,
            'stream' => false,
            'thinking' => ['type' => $thinking ? 'enabled' : 'disabled'],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(90)
                ->connectTimeout(10)
                ->post(rtrim($baseUrl, '/').'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            // Never expose the HTTP exception body, URL, prompt or API key.
            throw new PrismException('DeepSeek connection failed.', 0, $exception);
        }

        if (! $response->successful()) {
            // Status is safe to log; provider response bodies may contain prompts.
            throw new PrismException('DeepSeek rejected the report request.', $response->status());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(__('AI returned an empty or excessively long report.'));
        }

        $reason = data_get($data, 'choices.0.finish_reason');
        if ($reason === 'length') {
            throw new RuntimeException(__('The AI report was truncated. Increase the maximum token limit.'));
        }
        if ($reason !== 'stop') {
            // Refuse ambiguous results rather than storing an incomplete report.
            throw new RuntimeException('AI returned an unsupported finish reason.');
        }
        $body = data_get($data, 'choices.0.message.content');
        if (! is_string($body)) {
            throw new RuntimeException(__('AI returned an empty or excessively long report.'));
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 50000) {
            throw new RuntimeException(__('AI returned an empty or excessively long report.'));
        }

        return $body;
    }
}
