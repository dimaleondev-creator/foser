<?php

return [
    'enabled' => (bool) env('AI_ENABLED', true),
    'provider' => env('AI_PROVIDER', 'mock'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 800),
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    'timeout' => (int) env('AI_TIMEOUT', 15),
    'privacy_mode' => env('AI_PRIVACY_MODE', 'local'),
    'providers' => [
        'gemini' => ['key' => env('GEMINI_API_KEY'), 'model' => env('GEMINI_MODEL', 'gemini-1.5-flash')],
        'openai' => ['key' => env('OPENAI_API_KEY'), 'model' => env('OPENAI_MODEL', 'gpt-4o-mini')],
        'mistral' => ['key' => env('MISTRAL_API_KEY'), 'model' => env('MISTRAL_MODEL', 'mistral-small-latest')],
        'local' => ['url' => env('LOCAL_AI_URL', 'http://127.0.0.1:11434/api/generate'), 'model' => env('LOCAL_AI_MODEL', 'llama3.2')],
    ],
];
