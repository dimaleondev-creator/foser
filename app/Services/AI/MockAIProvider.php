<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;

class MockAIProvider implements AIProviderInterface
{
    public function answer(string $question, array $sources): string
    {
        $source = $sources[0];
        return $source['excerpt'].' Selon « '.$source['title'].' », cette information est publiée par le FOSER.';
    }
}
