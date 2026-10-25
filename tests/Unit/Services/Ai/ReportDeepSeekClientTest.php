<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\PrismReportGenerator;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Exceptions\PrismException;
use RuntimeException;
use Tests\TestCase;

final class ReportDeepSeekClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.ai_chat', [
            'provider' => 'deepseek',
            'api_key' => 'local-test-key',
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-flash',
            'max_tokens' => 1000,
            'report_max_tokens' => 4000,
            'report_thinking' => false,
            'debug_report_prompts' => false,
        ]);
    }

    public function test_report_sends_non_thinking_request_with_its_own_limit(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => "## Podsumowanie\n\nGotowe."]]],
        ], 200)]);
        $text = app(PrismReportGenerator::class)->generate('{"report_format":"project_summary"}', 'pl');
        $this->assertSame("## Podsumowanie\n\nGotowe.", $text);
        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === 'https://api.deepseek.com/v1/chat/completions'
                && $body['model'] === 'deepseek-flash'
                && $body['thinking'] === ['type' => 'disabled']
                && $body['max_tokens'] === 4000
                && $body['messages'][0]['role'] === 'system'
                && $body['messages'][1]['role'] === 'user'
                && str_contains($body['messages'][1]['content'], 'project_summary');
        });
    }

    public function test_length_is_reported_as_truncated_instead_of_unknown_finish_reason(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['finish_reason' => 'length', 'message' => ['content' => 'partial']]],
        ])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('truncated');
        app(PrismReportGenerator::class)->generate('{"report_format":"project_summary"}', 'pl');
    }

    public function test_provider_status_is_preserved_without_exposing_provider_body(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response(['error' => ['message' => 'SECRET RESPONSE']], 402)]);
        try {
            app(PrismReportGenerator::class)->generate('{"report_format":"project_summary"}', 'pl');
            $this->fail('Expected provider rejection');
        } catch (PrismException $e) {
            $this->assertSame(402, $e->getCode());
            $this->assertStringNotContainsString('SECRET RESPONSE', $e->getMessage());
        }
    }

    public function test_unknown_reason_never_saves_partial_output(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['finish_reason' => 'content_filter', 'message' => ['content' => 'partially generated']]],
        ])]);
        $this->expectException(RuntimeException::class);
        app(PrismReportGenerator::class)->generate('{"report_format":"project_summary"}', 'pl');
    }
}
