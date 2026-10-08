<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPartnersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_partners_lists_active_published_partners_only(): void
    {
        $partner = Partner::create(['name' => 'Université partenaire', 'slug' => 'universite-partenaire', 'description' => 'Partenaire académique.', 'category' => 'Académique', 'status' => 'published']);
        Partner::create(['name' => 'Partenaire brouillon', 'slug' => 'partenaire-brouillon', 'status' => 'draft']);
        Partner::create(['name' => 'Partenaire expiré', 'slug' => 'partenaire-expire', 'status' => 'published', 'ends_at' => today()->subDay()]);

        $this->get(route('partners.index'))->assertOk()->assertSee($partner->name)->assertDontSee('Partenaire brouillon')->assertDontSee('Partenaire expiré');
    }

    public function test_public_partners_can_be_searched_and_detail_is_public(): void
    {
        $partner = Partner::create(['name' => 'Institut de recherche', 'slug' => 'institut-recherche', 'description' => 'Soutien à la recherche.', 'status' => 'published']);
        Partner::create(['name' => 'Fondation éducation', 'slug' => 'fondation-education', 'status' => 'published']);

        $this->get(route('partners.index', ['q' => 'recherche']))->assertOk()->assertSee($partner->name)->assertDontSee('Fondation éducation');
        $this->get(route('partners.show', $partner->slug))->assertOk()->assertSee($partner->name)->assertSee($partner->description);
    }

    public function test_director_can_reach_partner_list_and_page_has_sticky_navigation_and_footer(): void
    {
        $director = User::factory()->create(['account_type' => 'directeur_general', 'status' => 'active']);
        $partner = Partner::create(['name' => 'Institut partenaire', 'slug' => 'institut-partenaire', 'status' => 'published']);

        $this->actingAs($director)->get(route('directeur_general.dashboard'))
            ->assertOk()->assertSee(route('partners.index'));
        $this->get(route('partners.index'))
            ->assertOk()->assertSee($partner->name)->assertSee('partner-header', false)
            ->assertSee('Tableau de direction')->assertSee('partner-footer', false)
            ->assertSee('Tous les partenaires');
    }

    public function test_draft_partner_detail_is_not_public(): void
    {
        $partner = Partner::create(['name' => 'Partenaire privé', 'slug' => 'partenaire-prive', 'status' => 'draft']);
        $this->get(route('partners.show', $partner->slug))->assertNotFound();
    }
}
