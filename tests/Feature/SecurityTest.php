<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $this->get('/student/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('student.login.store');

        $this->assertContains('throttle:6,1', $route->middleware());
    }

    public function test_statistics_api_requires_sanctum_and_permission(): void
    {
        $route = Route::getRoutes()->getByName('api.statistics');

        $this->assertContains('auth:sanctum', $route->middleware());
        $this->assertContains('permission:reports.view', $route->middleware());
        $this->getJson('/api/statistics')->assertUnauthorized();
    }

    public function test_private_storage_is_not_exposed_as_a_public_route(): void
    {
        $this->get('/storage/private/example.pdf')->assertNotFound();
    }
}
