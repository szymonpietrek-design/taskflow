<?php

return [
    // Count all requests, including failed/outcome-unknown: they might have consumed paid tokens.
    // Set either value to 0 to disable that particular limit. Enforced under project-row lock.
    'limits' => [
        'per_project_24h' => (int) env('AI_PR_MAX_PER_PROJECT_24H', 20),
        'per_project_1h' => (int) env('AI_PR_MAX_PER_PROJECT_1H', 5),
    ],

    // Optional indicative rates in USD per ONE MILLION tokens. No default prices:
    // do not silently show incorrect financial numbers when a provider changes prices.
    // Rates do not distinguish cache-hit/miss or any provider pricing tier.
    'prices' => [
        'deepseek' => [
            // Current DeepSeek models. Prices are intentionally NOT assumed;
            // peak/off-peak and input cache rates can change the invoice.
            'deepseek-flash' => [
                'input_per_million_usd' => env('AI_PR_DEEPSEEK_FLASH_INPUT_USD_PER_MILLION'),
                'output_per_million_usd' => env('AI_PR_DEEPSEEK_FLASH_OUTPUT_USD_PER_MILLION'),
            ],
            'deepseek-v4-pro' => [
                'input_per_million_usd' => env('AI_PR_DEEPSEEK_PRO_INPUT_USD_PER_MILLION'),
                'output_per_million_usd' => env('AI_PR_DEEPSEEK_PRO_OUTPUT_USD_PER_MILLION'),
            ],
            // Legacy historical snapshots; new installations should use a supported model.
            'deepseek-chat' => [
                'input_per_million_usd' => env('AI_PR_DEEPSEEK_CHAT_INPUT_USD_PER_MILLION'),
                'output_per_million_usd' => env('AI_PR_DEEPSEEK_CHAT_OUTPUT_USD_PER_MILLION'),
            ],
        ],
    ],
];
