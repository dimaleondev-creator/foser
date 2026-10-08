<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Partner;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Event;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_versioned_resource_requires_sanctum(): void
    {
        $this->getJson('/api/v1/programs')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('pagination', null);
    }

    public function test_versioned_login_validates_input_as_json(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Les données fournies sont invalides.')
            ->assertJsonPath('pagination', null);
    }

    public function test_api_login_rejects_accounts_that_are_not_active_and_is_rate_limited(): void
    {
        $pending = \App\Models\User::factory()->create([
            'email' => 'pending-researcher@example.test', 'password' => bcrypt('Strong-password-123'),
            'account_type' => 'researcher', 'status' => 'pending',
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => $pending->email, 'password' => 'Strong-password-123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $route = Route::getRoutes()->getByName('api.v1.auth.login');
        $this->assertContains('throttle:6,1', $route->middleware());
    }

    public function test_api_password_login_cannot_bypass_staff_mfa(): void
    {
        $staff = \App\Models\User::factory()->create([
            'email' => 'staff-api@example.test', 'password' => bcrypt('Strong-password-123'),
            'account_type' => 'agent_finance', 'status' => 'active',
        ]);
        $staff->assignRole('agent_finance');

        $this->postJson('/api/v1/auth/login', ['email' => $staff->email, 'password' => 'Strong-password-123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_login_rejects_brute_force_after_six_attempts(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong-password'])
            ->assertTooManyRequests();
    }

    public function test_sanctum_personal_access_tokens_expire(): void
    {
        config(['sanctum.expiration' => 60]);
        $user = \App\Models\User::factory()->create(['status' => 'active']);
        $plainTextToken = $user->createToken('security-test')->plainTextToken;
        $this->withToken($plainTextToken)->getJson('/api/v1/auth/me')->assertOk();

        $this->travel(61)->minutes();
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->withToken($plainTextToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
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
        foreach (['students', 'universities', 'researchers', 'programs', 'calls', 'applications', 'documents', 'evaluations', 'research-projects', 'payments', 'notifications', 'claims', 'results', 'awards', 'disbursements'] as $resource) {
            $this->assertNotNull(Route::getRoutes()->getByName('api.v1.' . $resource . '.index'));
        }
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.statistics'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.statistics.regions'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.search'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.events.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.partners.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.testimonials.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.news.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.documents.public.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.results.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.awards.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.disbursements.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('api.v1.users.me'));
    }

    public function test_openapi_spec_is_valid_yaml_and_documents_the_api_contract(): void
    {
        $spec = \Symfony\Component\Yaml\Yaml::parseFile(base_path('docs/openapi.yaml'));

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertArrayHasKey('/results', $spec['paths']);
        $this->assertArrayHasKey('/awards', $spec['paths']);
        $this->assertArrayHasKey('/disbursements', $spec['paths']);
        $this->assertArrayHasKey('/public/documents', $spec['paths']);
        $this->assertArrayHasKey('pagination', $spec['components']['schemas']['ApiResponse']['properties']);
        $this->assertArrayHasKey('success', $spec['components']['schemas']['ApiResponse']['properties']);
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

        $this->getJson('/api/v1/partners?per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $partner->id)
            ->assertJsonPath('data.0.name', 'Partenaire API')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('message', null)
            ->assertJsonPath('errors', null);
    }

    public function test_authenticated_user_endpoint_uses_resource_and_never_serializes_mfa_secrets(): void
    {
        $user = \App\Models\User::factory()->create(['status' => 'active']);
        $user->saveAppAuthenticationSecret('very-sensitive-mfa-secret');
        $user->saveAppAuthenticationRecoveryCodes(['sensitive-recovery-code']);
        $token = $user->createToken('user-resource-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.app_authentication_secret')
            ->assertJsonMissingPath('data.app_authentication_recovery_codes')
            ->assertJsonMissingPath('data.password')
            ->assertJsonPath('pagination', null);
    }

    public function test_public_news_api_filters_paginates_and_hides_unpublished_content(): void
    {
        $category = \App\Models\NewsCategory::create(['name' => 'Actualités API', 'slug' => 'actualites-api']);
        $published = News::create([
            'category_id' => $category->id, 'title' => 'Annonce publique', 'slug' => 'annonce-publique-api',
            'excerpt' => 'Résumé public', 'body' => 'Corps public', 'status' => 'published',
            'visibility' => 'public', 'published_at' => now(), 'image_path' => 'news/private-name.jpg',
        ]);
        News::create(['title' => 'Brouillon API privé', 'slug' => 'brouillon-api-prive', 'body' => 'Secret', 'status' => 'draft', 'visibility' => 'internal']);

        $this->getJson('/api/v1/news?q=Annonce&per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.excerpt', 'Résumé public')
            ->assertJsonMissingPath('data.0.body')
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('message', null);
        $this->getJson('/api/v1/news/annonce-publique-api')
            ->assertOk()->assertJsonPath('data.body', 'Corps public')->assertJsonPath('data.image_url', asset('storage/news/private-name.jpg'));
        $this->getJson('/api/v1/news/brouillon-api-prive')->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_public_documents_api_never_exposes_storage_paths(): void
    {
        $category = DocumentCategory::create(['name' => 'Lois API', 'slug' => 'lois-api']);
        $document = Document::create([
            'category_id' => $category->id, 'title' => 'Loi publique', 'description' => 'Texte officiel',
            'author' => 'FOSER', 'reference' => 'L-2026-01', 'document_type' => 'loi', 'year' => 2026,
            'language' => 'fr', 'disk' => 'local', 'path' => 'secret-storage/internal-path.pdf',
            'mime_type' => 'application/pdf', 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->getJson('/api/v1/public/documents?q=L-2026-01&category=lois-api&year=2026&language=fr')
            ->assertOk()
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.reference', 'L-2026-01')
            ->assertJsonPath('data.0.download_url', route('documents.download', $document))
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('data.0.disk');
    }

    public function test_public_event_catalog_supports_validated_filters_and_pagination(): void
    {
        $event = Event::create([
            'title' => 'Forum de recherche mobile', 'slug' => 'forum-recherche-mobile',
            'description' => 'Rencontre annuelle', 'category' => 'Recherche',
            'starts_at' => now()->addDays(3), 'status' => 'published',
        ]);
        Event::create(['title' => 'Forum éducation', 'slug' => 'forum-education-mobile', 'category' => 'Education', 'starts_at' => now()->addDays(4), 'status' => 'published']);

        $this->getJson('/api/v1/events?q=recherche&category=Recherche&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $event->id)
            ->assertJsonPath('pagination.per_page', 1)->assertJsonPath('success', true);
        $this->getJson('/api/v1/events?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
}
