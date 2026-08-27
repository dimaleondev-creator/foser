<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_and_readiness_endpoints_return_safe_statuses(): void
    {
        $this->getJson('/live')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/ready')->assertOk()->assertJsonPath('status', 'ok')->assertJsonStructure(['checks' => ['database', 'cache']]);
        $this->getJson('/health')->assertOk()->assertJsonStructure(['status', 'checks', 'timestamp']);
    }
}