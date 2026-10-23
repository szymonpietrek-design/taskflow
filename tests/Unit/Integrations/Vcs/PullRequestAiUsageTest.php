<?php

namespace Tests\Unit\Integrations\Vcs;

use App\Integrations\Vcs\Ai\PullRequestAiUsage;
use Tests\TestCase;

final class PullRequestAiUsageTest extends TestCase
{
    public function test_fixed_point_estimate_uses_actual_provider_model_and_token_counts(): void
    {
        config()->set('ai_pr.prices.deepseek.deepseek-chat', [
            'input_per_million_usd' => '0.250000',
            'output_per_million_usd' => '1.250000',
        ]);
        $usage = app(PullRequestAiUsage::class);
        $quote = $usage->quote('deepseek', 'deepseek-chat', 1000, 500);
        $this->assertSame('0.00087500', $quote['cost_usd']);
        $this->assertSame('0.250000', $quote['input_rate']);
        $this->assertNull($usage->quote('deepseek', 'unknown', 1000, 500));
        $this->assertNull($usage->quote('deepseek', 'deepseek-chat', null, 500));
    }

    public function test_zero_price_is_valid_but_missing_or_invalid_rate_is_not(): void
    {
        $usage = app(PullRequestAiUsage::class);
        config()->set('ai_pr.prices.deepseek.deepseek-chat', [
            'input_per_million_usd' => '0', 'output_per_million_usd' => '0',
        ]);
        $this->assertSame('0.00000000', $usage->quote('deepseek', 'deepseek-chat', 100, 200)['cost_usd']);
        config()->set('ai_pr.prices.deepseek.deepseek-chat.output_per_million_usd', 'not-a-price');
        $this->assertNull($usage->quote('deepseek', 'deepseek-chat', 100, 200));
        config()->set('ai_pr.prices.deepseek.deepseek-chat.output_per_million_usd', null);
        $this->assertNull($usage->quote('deepseek', 'deepseek-chat', 100, 200));
    }
}
