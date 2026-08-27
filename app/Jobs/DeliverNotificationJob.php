<?php

namespace App\Jobs;

use App\Services\NotificationService;
use App\Contracts\SmsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly string $deliveryId) {}

    public function handle(NotificationService $notifications, SmsProvider $sms): void
    {
        $notifications->deliver($this->deliveryId, $sms);
    }

    public function failed(?\Throwable $exception): void
    {
        app(NotificationService::class)->markFailed($this->deliveryId, $exception?->getMessage() ?: 'Échec de livraison.');
    }
}
