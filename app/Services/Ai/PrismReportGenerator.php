<?php

namespace App\Services\Ai;

use App\Contracts\Ai\ReportTextGenerator;
use InvalidArgumentException;

final class PrismReportGenerator implements ReportTextGenerator
{
    public function generate(string $context, string $language): string
    {
        if (! preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', $language) || strlen($language) > 35) {
            throw new InvalidArgumentException(__('Select a valid language code, such as en, pl or pt-BR.'));
        }

        // The same provider settings are used by release changelog generation.
        $settings = config('services.ai_chat', []);
        if (($settings['provider'] ?? null) !== 'deepseek') {
            throw new InvalidArgumentException(__('Configure AI_CHAT_PROVIDER=deepseek first.'));
        }
        $key = trim((string) ($settings['api_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException(__('Set DEEPSEEK_API_KEY in the local .env file.'));
        }
        $url = rtrim((string) ($settings['base_url'] ?? 'https://api.deepseek.com/v1'), '/');
        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException(__('DEEPSEEK_BASE_URL must use HTTPS.'));
        }
        $model = trim((string) ($settings['model'] ?? 'deepseek-chat'));
        if ($model === '') {
            throw new InvalidArgumentException(__('Choose a DeepSeek model in .env.'));
        }
        $maxTokens = max(200, min(8000, (int) ($settings['report_max_tokens'] ?? 4000)));

        $prompts = app(ReportPromptBuilder::class)->build($context, $language);

        // Explicit local-only capture, disabled unless AI_DEBUG_REPORT_PROMPTS=true.
        // The file contains unredacted report data: NEVER write it to Laravel logs.
        if (app()->environment('local') && (bool) config('services.ai_chat.debug_report_prompts', false)) {
            try {
                app(ReportPromptArchive::class)->save(
                    null, $language, $model, $maxTokens,
                    $prompts['system'], $prompts['user'],
                );
            } catch (\Throwable $exception) {
                // Debugging must not break the report generation.
                \Illuminate\Support\Facades\Log::warning('report_prompt_capture_failed', [
                    'exception_class' => $exception::class,
                ]);
            }
        }

        // Prism v0.100.1's DeepSeek text adapter rejects the provider's "length"
        // finish reason as an unknown response. It also cannot send the "thinking"
        // parameter. Use Laravel's HTTP client for reports ONLY; all other AI
        // generators continue to use Prism.
        return app(ReportDeepSeekClient::class)->generate(
            baseUrl: $url,
            apiKey: $key,
            model: $model,
            systemPrompt: $prompts['system'],
            userPrompt: $prompts['user'],
            maxTokens: $maxTokens,
            thinking: (bool) ($settings['report_thinking'] ?? false),
        );
    }
}
