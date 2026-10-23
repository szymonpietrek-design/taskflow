<?php

namespace App\Integrations\Vcs\Ai;

use App\Models\Project;
use App\Models\PullRequestAiAnalysis;

/** Small, provider-neutral quota + persisted-cost calculator. No raw evidence is handled here. */
final class PullRequestAiUsage
{
    /** @return array{cost_usd: string, input_rate: string, output_rate: string}|null */
    public function quote(string $provider, string $model, ?int $promptTokens, ?int $completionTokens): ?array
    {
        if ($promptTokens === null || $completionTokens === null || $promptTokens < 0 || $completionTokens < 0) {
            return null;
        }
        $prices = config('ai_pr.prices.'.strtolower($provider).'.'.strtolower($model));
        if (! is_array($prices)) {
            return null;
        }
        $input = $this->rate($prices['input_per_million_usd'] ?? null);
        $output = $this->rate($prices['output_per_million_usd'] ?? null);
        if ($input === null || $output === null) {
            return null;
        }
        // Fixed-point micro-dollars per million tokens; amount rounded to 8 decimals USD.
        // Typical token counts are small. Reject extreme counts to avoid integer overflow.
        if ($promptTokens > 10000000 || $completionTokens > 10000000) {
            return null;
        }
        $costIn100Millionths = intdiv($promptTokens * $input['units'] + $completionTokens * $output['units'] + 5000, 10000);
        return [
            'cost_usd' => sprintf('%d.%08d', intdiv($costIn100Millionths, 100000000), $costIn100Millionths % 100000000),
            'input_rate' => $input['decimal'],
            'output_rate' => $output['decimal'],
        ];
    }

    /** @return array{units:int,decimal:string}|null */
    private function rate(mixed $value): ?array
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        $text = trim((string) $value);
        if (! preg_match('/\A(?:0|[1-9]\d{0,2}|1000)(?:\.\d{1,6})?\z/', $text)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $units = (int) $whole * 1000000 + (int) str_pad($fraction, 6, '0');
        if ($units > 1000000000) {
            return null;
        }
        return ['units' => $units, 'decimal' => sprintf('%d.%06d', intdiv($units, 1000000), $units % 1000000)];
    }

    /** @return array{used_24h:int,used_1h:int,limit_24h:int,limit_1h:int,allowed:bool} */
    public function quota(Project $project): array
    {
        $limit24 = max(0, min(10000, (int) config('ai_pr.limits.per_project_24h', 20)));
        $limit1 = max(0, min(10000, (int) config('ai_pr.limits.per_project_1h', 5)));
        $query = PullRequestAiAnalysis::query()->where('project_id', $project->id);
        // Rolling windows, not calendar days; failed or unknown outcomes also count.
        $used24 = (clone $query)->where('created_at', '>=', now()->subHours(24))->count();
        $used1 = (clone $query)->where('created_at', '>=', now()->subHour())->count();
        return [
            'used_24h' => $used24, 'used_1h' => $used1,
            'limit_24h' => $limit24, 'limit_1h' => $limit1,
            'allowed' => ($limit24 === 0 || $used24 < $limit24) && ($limit1 === 0 || $used1 < $limit1),
        ];
    }

    /** @return array{completed:int,failed:int,unknown_cost:int,prompt_tokens:int,completion_tokens:int,estimated_cost_usd:string|null} */
    public function last30Days(Project $project): array
    {
        $rows = PullRequestAiAnalysis::query()->where('project_id', $project->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND estimated_cost_usd IS NULL THEN 1 ELSE 0 END) as unknown_cost_count")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN COALESCE(prompt_tokens, 0) ELSE 0 END) as prompt_total")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN COALESCE(completion_tokens, 0) ELSE 0 END) as completion_total")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN COALESCE(estimated_cost_usd, 0) ELSE 0 END) as estimated_total")
            ->first();
        $completed = (int) ($rows?->completed_count ?? 0);
        $known = $completed - (int) ($rows?->unknown_cost_count ?? 0);
        return [
            'completed' => $completed, 'failed' => (int) ($rows?->failed_count ?? 0),
            'unknown_cost' => (int) ($rows?->unknown_cost_count ?? 0),
            'prompt_tokens' => (int) ($rows?->prompt_total ?? 0),
            'completion_tokens' => (int) ($rows?->completion_total ?? 0),
            // Estimate is partial if any rows lack cost; UI includes an explicit caveat.
            'estimated_cost_usd' => $known > 0 ? number_format((float) ($rows?->estimated_total ?? 0), 8, '.', '') : null,
        ];
    }
}
