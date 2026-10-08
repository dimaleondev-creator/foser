<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResearcherPortalExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_researcher_can_view_dashboard_calls_and_profile(): void
    {
        $researcher = $this->researcher();
        $this->actingAs($researcher)->get('/researcher')->assertOk()->assertSee('ESPACE DE RECHERCHE')->assertSee('Mes projets et candidatures')->assertSee('Calendrier de recherche');
        $this->actingAs($researcher)->get('/researcher/calls')->assertOk()->assertSee('APPELS DE RECHERCHE');
        $this->actingAs($researcher)->get('/researcher/profile')->assertOk()->assertSee('Profil scientifique complété');
    }

    public function test_researcher_can_open_new_project_form_before_dynamic_project_route(): void
    {
        $researcher = $this->researcher();

        $this->actingAs($researcher)->get(route('researcher.projects.create-form'))
            ->assertOk()->assertSee('Préparer une proposition.')->assertSee('Enregistrer le brouillon');
    }

    public function test_researcher_without_a_laboratory_sees_an_empty_state_instead_of_a_404(): void
    {
        $researcher = $this->researcher();

        $this->actingAs($researcher)->get(route('researcher.laboratory'))
            ->assertOk()->assertSee('Aucun laboratoire associé')->assertSee(route('researcher.profile'));
    }

    public function test_researcher_workspace_tabs_load_and_only_show_their_own_records(): void
    {
        $owner = $this->researcher();
        $other = $this->researcher();
        $ownerProjectId = (string) Str::uuid();
        $otherProjectId = (string) Str::uuid();
        DB::table('research_projects')->insert([
            ['id' => $ownerProjectId, 'principal_researcher_id' => $owner->id, 'title' => 'Projet privé propriétaire', 'reference' => 'TAB-'.Str::random(6), 'status' => 'evaluation', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $otherProjectId, 'principal_researcher_id' => $other->id, 'title' => 'Projet privé autre', 'reference' => 'TAB-'.Str::random(6), 'status' => 'funded', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('research_project_evaluations')->insert([
            ['id' => (string) Str::uuid(), 'research_project_id' => $ownerProjectId, 'evaluator_id' => $other->id, 'status' => 'completed', 'score' => 99, 'comment' => 'Évaluation confidentielle propriétaire', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'research_project_id' => $otherProjectId, 'evaluator_id' => $owner->id, 'status' => 'assigned', 'score' => null, 'comment' => 'Évaluation étrangère', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $ownerDocumentId = (string) Str::uuid();
        $otherDocumentId = (string) Str::uuid();
        DB::table('documents')->insert([
            ['id' => $ownerDocumentId, 'uploaded_by' => $owner->id, 'title' => 'Document propriétaire', 'document_type' => 'report', 'disk' => 'private', 'path' => 'owner/report.pdf', 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $otherDocumentId, 'uploaded_by' => $other->id, 'title' => 'Document étranger', 'document_type' => 'report', 'disk' => 'private', 'path' => 'other/report.pdf', 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('research_project_documents')->insert([
            ['id' => (string) Str::uuid(), 'research_project_id' => $ownerProjectId, 'document_id' => $ownerDocumentId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'research_project_id' => $otherProjectId, 'document_id' => $otherDocumentId, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('financial_commitments')->insert([
            ['id' => (string) Str::uuid(), 'research_project_id' => $ownerProjectId, 'reference' => 'FIN-'.Str::random(6), 'amount' => 123000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'research_project_id' => $otherProjectId, 'reference' => 'FIN-'.Str::random(6), 'amount' => 987000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('notifications')->insert([
            ['id' => (string) Str::uuid(), 'type' => 'owner.notice', 'notifiable_type' => User::class, 'notifiable_id' => $owner->id, 'data' => json_encode(['subject' => 'Notification propriétaire']), 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'type' => 'other.notice', 'notifiable_type' => User::class, 'notifiable_id' => $other->id, 'data' => json_encode(['subject' => 'Notification étrangère']), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $programId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme calendrier', 'code' => 'CAL-'.Str::random(6), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'title' => 'Appel calendrier', 'reference' => 'CAL-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDays(8), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($owner)->get(route('researcher.workspace.section', 'projects'))->assertOk()->assertSee('Projet privé propriétaire')->assertDontSee('Projet privé autre');
        $this->get(route('researcher.workspace.section', 'applications'))->assertOk()->assertSee('Projet privé propriétaire')->assertDontSee('Projet privé autre');
        $this->get(route('researcher.workspace.section', 'evaluations'))->assertOk()->assertSee('Projet privé propriétaire')->assertDontSee('Évaluation confidentielle propriétaire')->assertDontSee('Projet privé autre');
        $this->get(route('researcher.workspace.section', 'finance'))->assertOk()->assertSee('123 000')->assertDontSee('987 000')->assertSee('Projet privé propriétaire');
        $this->get(route('researcher.workspace.section', 'documents'))->assertOk()->assertSee('Document propriétaire')->assertDontSee('Document étranger');
        $this->get(route('researcher.workspace.section', 'notifications'))->assertOk()->assertSee('Notification propriétaire')->assertDontSee('Notification étrangère');
        $this->get(route('researcher.workspace.section', 'calendar'))->assertOk()->assertSee('Appel calendrier');
    }

    public function test_dashboard_uses_only_visible_projects_and_real_financial_data(): void
    {
        $researcher = $this->researcher();
        $other = $this->researcher();
        $programId = (string) Str::uuid();
        $researchProgramId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme scientifique réel', 'code' => 'SCI-'.Str::random(6), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('research_programs')->insert(['id' => $researchProgramId, 'program_id' => $programId, 'research_area' => 'Agronomie', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'title' => 'Appel de recherche actuellement ouvert', 'reference' => 'CALL-'.Str::random(7), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDays(10), 'status' => 'published', 'available_budget' => 800000, 'maximum_project_amount' => 250000, 'domains' => 'Agronomie', 'created_at' => now(), 'updated_at' => now()]);
        $ownedProjectId = (string) Str::uuid();
        $foreignProjectId = (string) Str::uuid();
        DB::table('research_projects')->insert([
            ['id' => $ownedProjectId, 'research_program_id' => $researchProgramId, 'principal_researcher_id' => $researcher->id, 'title' => 'Projet agricole du chercheur', 'reference' => 'OWN-'.Str::random(7), 'status' => 'funded', 'budget' => 500000, 'currency' => 'FCFA', 'domain' => 'Agronomie', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $foreignProjectId, 'research_program_id' => null, 'principal_researcher_id' => $other->id, 'title' => 'Projet confidentiel étranger', 'reference' => 'OTH-'.Str::random(7), 'status' => 'funded', 'budget' => 9900000, 'currency' => 'FCFA', 'domain' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $commitmentId = (string) Str::uuid();
        $disbursementId = (string) Str::uuid();
        DB::table('financial_commitments')->insert([
            ['id' => $commitmentId, 'research_project_id' => $ownedProjectId, 'reference' => 'ENG-'.Str::random(7), 'amount' => 300000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'research_project_id' => $foreignProjectId, 'reference' => 'ENG-'.Str::random(7), 'amount' => 9900000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('disbursements')->insert(['id' => $disbursementId, 'commitment_id' => $commitmentId, 'reference' => 'DIS-'.Str::random(7), 'amount' => 125000, 'status' => 'paid', 'scheduled_for' => today(), 'disbursed_at' => today(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('research_project_disbursements')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $ownedProjectId, 'disbursement_id' => $disbursementId, 'created_at' => now(), 'updated_at' => now()]);
        $documentId = (string) Str::uuid();
        DB::table('documents')->insert(['id' => $documentId, 'uploaded_by' => $researcher->id, 'title' => 'Protocole agricole validé', 'document_type' => 'protocol', 'disk' => 'private', 'path' => 'research/protocol.pdf', 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('research_project_documents')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $ownedProjectId, 'document_id' => $documentId, 'document_role' => 'scientific', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('research_publications')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $ownedProjectId, 'author_id' => $researcher->id, 'title' => 'Résultats de recherche agricole', 'publication_type' => 'article', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('research_project_evaluations')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $ownedProjectId, 'evaluator_id' => $other->id, 'score' => 88, 'status' => 'completed', 'comment' => 'Commentaire confidentiel', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'researcher.dashboard.notice', 'notifiable_type' => User::class, 'notifiable_id' => $researcher->id, 'data' => json_encode(['subject' => 'Avis du projet agricole']), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($researcher)->get('/researcher')->assertOk()
            ->assertSee('Projet agricole du chercheur')->assertSee('Programme scientifique réel')
            ->assertSee('500 000')->assertSee('300 000')->assertSee('125 000')->assertSee('Avis du projet agricole')
            ->assertSee('Appel de recherche actuellement ouvert')->assertSee('Protocole agricole validé')->assertSee('Résultats de recherche agricole')
            ->assertSee('Évaluation')->assertDontSee('Projet confidentiel étranger')
            ->assertDontSee('Commentaire confidentiel')->assertDontSee($other->name);
    }

    public function test_researcher_cannot_access_another_researchers_project(): void
    {
        $owner = $this->researcher();
        $other = $this->researcher();
        $projectId = (string) Str::uuid();
        DB::table('research_projects')->insert(['id' => $projectId, 'principal_researcher_id' => $owner->id, 'title' => 'Projet privé', 'reference' => 'PRIV-'.Str::random(8), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($other)->get('/researcher/projects/'.$projectId)->assertNotFound();
        Sanctum::actingAs($other, ['research.view']);
        $this->getJson('/api/v1/researchers/projects/'.$projectId)->assertNotFound();
    }

    public function test_researcher_api_returns_only_owned_projects(): void
    {
        $researcher = $this->researcher();
        $other = $this->researcher();
        DB::table('research_projects')->insert([
            ['id' => (string) Str::uuid(), 'principal_researcher_id' => $researcher->id, 'title' => 'Projet personnel', 'reference' => 'OWN-'.Str::random(8), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'principal_researcher_id' => $other->id, 'title' => 'Projet autre', 'reference' => 'OTH-'.Str::random(8), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Sanctum::actingAs($researcher, ['research.view']);
        $response = $this->getJson('/api/v1/researchers/projects')->assertOk();
        $response->assertJsonFragment(['title' => 'Projet personnel'])->assertJsonMissing(['title' => 'Projet autre']);
        $this->getJson('/api/v1/researchers/me')->assertOk()->assertJsonPath('data.id', $researcher->id);
    }

    public function test_researcher_api_rejects_a_laboratory_from_another_university(): void
    {
        $researcher = $this->researcher();
        $universityA = (string) Str::uuid();
        $universityB = (string) Str::uuid();
        DB::table('universities')->insert([
            ['id' => $universityA, 'name' => 'Institution API A', 'code' => 'API-A-'.Str::random(5), 'country' => 'Burkina Faso', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $universityB, 'name' => 'Institution API B', 'code' => 'API-B-'.Str::random(5), 'country' => 'Burkina Faso', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('researcher_profiles')->where('user_id', $researcher->id)->update(['university_id' => $universityA]);
        $laboratoryId = (string) Str::uuid();
        DB::table('laboratories')->insert(['id' => $laboratoryId, 'university_id' => $universityB, 'name' => 'Laboratoire externe', 'code' => 'LAB-API-'.Str::random(5), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($researcher, ['research.view', 'research.manage']);

        $this->postJson('/api/v1/researchers/projects', ['title' => 'Projet transversal', 'laboratory_id' => $laboratoryId])->assertStatus(422);

        $this->assertDatabaseMissing('research_projects', ['principal_researcher_id' => $researcher->id, 'title' => 'Projet transversal']);
    }

    public function test_researcher_without_university_cannot_assign_another_university_laboratory(): void
    {
        $researcher = $this->researcher();
        $universityId = (string) Str::uuid();
        DB::table('universities')->insert([
            'id' => $universityId,
            'name' => 'Université externe',
            'code' => 'EXT-'.Str::random(6),
            'country' => 'Burkina Faso',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $laboratoryId = (string) Str::uuid();
        DB::table('laboratories')->insert([
            'id' => $laboratoryId,
            'university_id' => $universityId,
            'name' => 'Laboratoire externe',
            'code' => 'LAB-EXT-'.Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($researcher)->put(route('researcher.profile.update'), [
            'university_id' => $universityId,
            'laboratory_id' => $laboratoryId,
        ])->assertSessionHasErrors(['university_id', 'laboratory_id']);
        $this->assertDatabaseHas('researcher_profiles', ['user_id' => $researcher->id, 'university_id' => null, 'laboratory_id' => null]);

        $this->actingAs($researcher)->post(route('researcher.projects.create'), ['title' => 'Projet web non autorisé', 'laboratory_id' => $laboratoryId])
            ->assertStatus(422);
        $this->assertDatabaseMissing('research_projects', ['principal_researcher_id' => $researcher->id, 'title' => 'Projet web non autorisé']);

        Sanctum::actingAs($researcher, ['research.view', 'research.manage']);
        $this->postJson('/api/v1/researchers/projects', ['title' => 'Projet API non autorisé', 'laboratory_id' => $laboratoryId])
            ->assertStatus(422);
        $this->assertDatabaseMissing('research_projects', ['principal_researcher_id' => $researcher->id, 'title' => 'Projet API non autorisé']);
    }

    private function researcher(): User
    {
        $researcher = User::factory()->create(['account_type' => 'researcher', 'status' => 'active', 'email_verified_at' => now()]);
        $researcher->assignRole('researcher');
        DB::table('researcher_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $researcher->id, 'researcher_number' => 'CHR-'.Str::random(8), 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        return $researcher;
    }
}
