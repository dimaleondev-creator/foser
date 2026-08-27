<?php

namespace App\Services\Providers;

use App\Contracts\SmsProvider;
use RuntimeException;

class NullSmsProvider implements SmsProvider
{
    public function send(string $recipient, string $message): void
    {
        throw new RuntimeException('Aucun fournisseur SMS n’est configuré.');
    }
}
