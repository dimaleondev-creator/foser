<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee(route('student.login'))->assertSee(route('student.register'));
    }

    public function test_the_filament_login_page_is_available(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }
}
