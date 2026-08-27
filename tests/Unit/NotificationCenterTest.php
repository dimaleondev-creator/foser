<?php

namespace Tests\Unit;

use App\Jobs\DeliverNotificationJob;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationCenterTest extends TestCase
{
    public function test_all_notification_channels_are_declared(): void
    {
        $this->assertSame(['internal', 'email', 'sms', 'whatsapp'], NotificationService::CHANNELS);
    }

    public function test_delivery_job_is_retryable(): void
    {
        $job = new DeliverNotificationJob('delivery-id');

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff);
    }
}
