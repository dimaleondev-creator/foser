<?php

namespace App\Contracts;

interface AIProviderInterface
{
    public function answer(string $question, array $sources): string;
}
