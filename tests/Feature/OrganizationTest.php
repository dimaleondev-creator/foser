<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_organization_is_loaded_from_database_and_localized(): void
    {
        $board = OrganizationUnit::create(['name_fr' => 'Conseil d’administration', 'name_en' => 'Board of Directors', 'sort_order' => 1]);
        OrganizationUnit::create(['parent_id' => $board->id, 'name_fr' => 'Direction générale', 'name_en' => 'General Management', 'sort_order' => 1, 'function_fr' => 'Pilotage']);
        OrganizationUnit::create(['name_fr' => 'Structure inactive', 'name_en' => 'Inactive structure', 'is_active' => false]);
        app()->setLocale('en');

        $this->get('/organisation')->assertOk()->assertSee('Board of Directors')->assertSee('General Management')->assertDontSee('Inactive structure');
    }
}
