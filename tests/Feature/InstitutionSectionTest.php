<?php

namespace Tests\Feature;

use App\Models\InstitutionValue;
use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_institution_sections_are_accessible(): void
    {
        $this->get('/le-foser')->assertOk()->assertSee('Le FOSER');

        foreach (['historique', 'missions', 'vision', 'valeurs', 'organigramme', 'conseil-administration', 'direction-generale', 'directions', 'documents', 'rapports-annuels'] as $section) {
            $this->get('/le-foser/'.$section)->assertOk();
        }
    }

    public function test_values_and_typed_organization_units_are_displayed(): void
    {
        InstitutionValue::create(['name' => 'Intégrité', 'description' => 'Une gestion responsable.', 'is_active' => true]);
        OrganizationUnit::create(['name_fr' => 'Direction de la recherche', 'unit_type' => 'direction', 'is_active' => true]);

        $this->get('/le-foser/valeurs')->assertOk()->assertSee('Intégrité');
        $this->get('/le-foser/directions')->assertOk()->assertSee('Direction de la recherche');
    }

    public function test_inactive_institution_values_are_not_public(): void
    {
        InstitutionValue::create(['name' => 'Valeur masquée', 'description' => 'Non publiée.', 'is_active' => false]);

        $this->get('/le-foser/valeurs')->assertOk()->assertDontSee('Valeur masquée');
    }
}