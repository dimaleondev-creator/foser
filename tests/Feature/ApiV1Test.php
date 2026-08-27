<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Partner;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_resource_requires_sanctum(): void
    {
        $this->getJson('/api/v1/programs')->assertUnauthorized();
    }

    public function test_versioned_login_validates_input_as_json(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_versioned_routes_use_rate_limiting_and_permissions(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.programs.index');

        $this->assertContains('auth:sanctum', $route->middleware());
        $this->assertContains('throttle:api', $route->middleware());
        $this->assertContains('permission:programs.view', $route->middleware());
    }

    public function test_all_requested_resource_routes_are_registered(): void
    {
        foreach (['students', 'universities', 'researchers', 'programs', 'calls', 'applications', 'documents', 'evaluations', 'research-projects', 'payments', 'notifications', 'claims'] as $resource) {
            $this->assertNotNull(Route::getRoutes()->getByName('api.v1.' . $resource . '.index'));
        }
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.statistics'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.statistics.regions'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.search'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.events.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.partners.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.testimonials.index'));
    }

    public function test_regional_statistics_is_rate_limited_and_returns_aggregates(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.statistics.regions');

        $this->assertContains('throttle:api', $route->middleware());
        $this->getJson('/api/v1/statistics/regions')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_global_search_validates_query_and_returns_public_results(): void
    {
        $this->getJson('/api/v1/search')->assertUnprocessable()->assertJsonValidationErrors(['q']);

        News::create(['title' => 'Recherche scientifique', 'slug' => 'recherche-scientifique', 'body' => 'Contenu publié.', 'status' => 'published', 'published_at' => now()]);
        $this->getJson('/api/v1/search?q=scientifique')->assertOk()->assertJsonPath('data.0.type', 'news')->assertJsonPath('data.0.title', 'Recherche scientifique');
    }

    public function test_public_catalog_returns_only_published_partner_fields(): void
    {
        $partner = Partner::create(['name' => 'Partenaire API', 'slug' => 'partenaire-api', 'description' => 'Visible publiquement.', 'email' => 'interne@example.test', 'status' => 'published']);
        Partner::create(['name' => 'Brouillon API', 'slug' => 'brouillon-api', 'status' => 'draft']);

        $this->getJson('/api/v1/partners?per_page=1')->assertOk()->assertJsonPath('data.0.id', $partner->id)->assertJsonPath('data.0.name', 'Partenaire API')->assertJsonMissingPath('data.0.email')->assertJsonPath('meta.per_page', 1);
    }
}
