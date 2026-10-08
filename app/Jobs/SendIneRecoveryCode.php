<?php

namespace App\Jobs;

use App\Mail\IneRecoveryCodeMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class SendIneRecoveryCode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId, public string $encryptedCode, public int $expiresAt) {}

    public function handle(): void
    {
        if ($this->expiresAt < now()->timestamp) {
            return;
        }

        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        Mail::mailer('smtp')->to($user->email)->send(new IneRecoveryCodeMail($user->name, Crypt::decryptString($this->encryptedCode)));
    }
}