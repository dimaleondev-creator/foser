<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use App\Services\AI\MockAIProvider;
use App\Services\AI\RemoteAIProvider;
use RuntimeException;

class AIService implements AIProviderInterface
{
    public function __construct(private readonly MockAIProvider $fallback) {}

    public function answer(string $question, array $sources): string
    {
        if (! config('ai.enabled', false) || $sources === []) {
            throw new RuntimeException('Aucune source officielle suffisante pour répondre.');
        }

        $provider = (string) config('ai.provider', 'mock');
        if ($provider === 'mock' || ($provider === 'local' && blank(config('ai.providers.local.model')))) {
            return $this->fallback->answer($question, $sources);
        }

        $key = config('ai.providers.'.$provider.'.key');
        if (in_array($provider, ['gemini', 'openai', 'mistral'], true) && blank($key)) {
            return $this->fallback->answer($question, $sources);
        }

        try {
            return app(RemoteAIProvider::class, ['provider' => $provider])->answer($question, $sources);
        } catch (RuntimeException) {
            return $this->fallback->answer($question, $sources);
        }
    }
}
