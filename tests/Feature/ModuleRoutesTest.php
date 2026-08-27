<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleRoutesTest extends TestCase
{
    public function test_core_portal_routes_are_registered(): void
    {
        foreach (['student.register', 'student.login', 'researcher.dashboard', 'researcher.projects.update', 'researcher.projects.documents.download', 'university.dashboard', 'university.imports.students.preview', 'university.reports.export', 'university.messages.index', 'calls.index', 'api.statistics', 'notifications.history', 'notifications.read', 'notifications.read-all'] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), $name.' route is missing');
        }
    }

    public function test_sensitive_module_routes_have_authentication_or_permission_middleware(): void
    {
        foreach (['researcher.dashboard', 'university.dashboard', 'notifications.history', 'api.statistics'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->middleware();
            $this->assertTrue(collect($middleware)->contains(fn (string $item): bool => str_contains($item, 'auth') || str_contains($item, 'permission')), $name.' is not protected');
        }
    }
}
