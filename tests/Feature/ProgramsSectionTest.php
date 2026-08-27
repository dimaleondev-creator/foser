<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ProgramFaq;
use App\Models\InnovationProgram;
use App\Services\StudyLoanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgramsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_catalog_and_all_required_sections_are_accessible(): void
    {
        $this->get('/programmes')->assertOk()->assertSee('Nos programmes')->assertSee('Aides financières')->assertSee('Prêts d’études')->assertSee('Recherche')->assertSee('Innovation');

        foreach ([
            '/programmes/aides-financieres/conditions', '/programmes/aides-financieres/montants', '/programmes/aides-financieres/procedure', '/programmes/aides-financieres/faq',
            '/programmes/prets-etudes/eligibilite', '/programmes/prets-etudes/simulation', '/programmes/prets-etudes/procedure',
            '/programmes/recherche/financement', '/programmes/recherche/appels', '/programmes/recherche/projets-finances',
            '/programmes/innovation/concours', '/programmes/innovation/incubation', '/programmes/innovation/valorisation',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_faq_is_published_from_database(): void
    {
        ProgramFaq::create(['id' => (string) Str::uuid(), 'category' => 'aides-financieres', 'question' => 'Qui peut demander une aide ?', 'answer' => 'Les étudiants éligibles.', 'status' => 'published']);

        $this->get('/programmes/aides-financieres/faq')->assertOk()->assertSee('Qui peut demander une aide ?')->assertSee('Les étudiants éligibles.');
    }

    public function test_faq_can_be_searched(): void
    {
        ProgramFaq::create(['id' => (string) Str::uuid(), 'category' => 'aides-financieres', 'question' => 'Quel montant ?', 'answer' => 'Le plafond dépend du dispositif.', 'status' => 'published']);

        $this->get('/programmes/aides-financieres/faq?q=plafond')->assertOk()->assertSee('Quel montant ?');
    }

    public function test_public_loan_simulation_returns_calculation(): void
    {
        $response = $this->postJson('/programmes/prets-etudes/simulation', ['amount' => 100000, 'duration_months' => 12, 'interest_rate' => 12, 'grace_period_months' => 0]);

        $response->assertOk()->assertJsonStructure(['monthly_payment', 'total_interest', 'total_repayment', 'schedule']);
        $this->assertEquals(12, count($response->json('schedule')));
    }

    public function test_innovation_subsections_use_their_french_category(): void
    {
        $program = Program::create(['name' => 'Innovation test', 'code' => 'INNO-TEST', 'type' => 'innovation', 'status' => 'published']);
        InnovationProgram::create(['program_id' => $program->id, 'innovation_area' => 'Concours numérique', 'innovation_type' => 'contest', 'status' => 'active']);

        $this->get('/programmes/innovation/concours')->assertOk()->assertSee('Concours numérique');
    }

    public function test_research_funding_and_innovation_records_have_public_details(): void
    {
        $program = Program::create(['name' => 'Recherche test', 'code' => 'RECH-TEST', 'type' => 'research', 'status' => 'published']);
        $funding = \App\Models\ResearchProgram::create(['program_id' => $program->id, 'research_area' => 'Santé numérique', 'description' => 'Financement public.', 'status' => 'active']);
        $innovationProgram = Program::create(['name' => 'Innovation détail', 'code' => 'INNO-DETAIL', 'type' => 'innovation', 'status' => 'published']);
        $innovation = InnovationProgram::create(['program_id' => $innovationProgram->id, 'innovation_area' => 'Prototype agricole', 'innovation_type' => 'valorization', 'description' => 'Innovation valorisée.', 'status' => 'active']);

        $this->get('/programmes/recherche/financement?q=Santé')->assertOk()->assertSee('Santé numérique')->assertSee(route('programs.research-funding.detail', ['category' => 'recherche', 'section' => 'financement', 'item' => $funding->id]));
        $this->get(route('programs.research-funding.detail', ['category' => 'recherche', 'section' => 'financement', 'item' => $funding->id]))->assertOk()->assertSee('Financement public.');
        $this->get(route('programs.innovation.detail', ['category' => 'innovation', 'section' => 'valorisation', 'item' => $innovation->id]))->assertOk()->assertSee('Innovation valorisée.');
    }

    public function test_invalid_loan_simulation_is_rejected(): void
    {
        $this->postJson('/programmes/prets-etudes/simulation', ['amount' => 0, 'duration_months' => 12, 'interest_rate' => 5])->assertUnprocessable();
    }
}
