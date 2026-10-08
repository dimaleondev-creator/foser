<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_owner_can_upload_replace_and_delete_a_document_on_an_editable_application(): void
    {
        [$student, $applicationId, $programId] = $this->studentApplication();
        DB::table('required_documents')->insert([
            'id' => (string) Str::uuid(), 'program_id' => $programId, 'document_type' => 'identite',
            'label' => 'Pièce d’identité', 'is_required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($student)->get('/student/documents?application_id='.$applicationId)
            ->assertOk()->assertSee('Documents requis')->assertSee('Pièces manquantes : identite');
        $this->actingAs($student)->post('/student/documents', [
            'application_id' => $applicationId,
            'document_type' => 'identite',
            'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $documentId = (string) DB::table('application_documents')->where('application_id', $applicationId)->value('document_id');
        $oldPath = DB::table('documents')->where('id', $documentId)->value('path');
        Storage::disk('local')->assertExists($oldPath);
        DB::table('application_documents')->where('document_id', $documentId)->update([
            'status' => 'validated', 'comment' => 'Contrôlée', 'verified_by' => $student->id,
            'verified_at' => now(),
        ]);

        $this->put('/student/documents/'.$documentId, [
            'file' => UploadedFile::fake()->create('identity-replaced.pdf', 120, 'application/pdf'),
        ])->assertRedirect();
        $replacement = DB::table('documents')->where('id', $documentId)->first();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($replacement->path);
        $this->assertDatabaseHas('application_documents', [
            'document_id' => $documentId, 'status' => 'submitted', 'comment' => null, 'verified_at' => null,
        ]);

        $this->delete('/student/documents/'.$documentId)->assertRedirect();
        $this->assertSoftDeleted('documents', ['id' => $documentId]);
        $this->assertDatabaseMissing('application_documents', ['document_id' => $documentId]);
        Storage::disk('local')->assertMissing($replacement->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application.document_replaced', 'user_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application.document_deleted', 'user_id' => $student->id]);
    }

    public function test_student_cannot_use_another_students_document_or_change_documents_after_submission(): void
    {
        [$owner, $applicationId] = $this->studentApplication();
        [$other] = $this->studentApplication();
        $this->actingAs($owner)->post('/student/documents', [
            'application_id' => $applicationId,
            'document_type' => 'identity',
            'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $documentId = (string) DB::table('application_documents')->where('application_id', $applicationId)->value('document_id');

        $this->actingAs($other)->put('/student/documents/'.$documentId, [
            'file' => UploadedFile::fake()->create('stolen.pdf', 100, 'application/pdf'),
        ])->assertNotFound();
        $this->actingAs($other)->delete('/student/documents/'.$documentId)->assertNotFound();

        DB::table('applications')->where('id', $applicationId)->update(['status' => 'soumis']);
        $this->actingAs($owner)->put('/student/documents/'.$documentId, [
            'file' => UploadedFile::fake()->create('late.pdf', 100, 'application/pdf'),
        ])->assertNotFound();
        $this->actingAs($owner)->delete('/student/documents/'.$documentId)->assertNotFound();
    }

    public function test_upload_rejects_unrequested_types_invalid_formats_and_files_over_ten_megabytes(): void
    {
        [$student, $applicationId, $programId] = $this->studentApplication();
        DB::table('required_documents')->insert([
            'id' => (string) Str::uuid(), 'program_id' => $programId, 'document_type' => 'identite',
            'label' => 'Pièce d’identité', 'is_required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->post('/student/documents', [
            'application_id' => $applicationId,
            'document_type' => 'autre',
            'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('document_type');
        $this->post('/student/documents', [
            'application_id' => $applicationId,
            'document_type' => 'identite',
            'file' => UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream'),
        ])->assertSessionHasErrors('file');
        $this->post('/student/documents', [
            'application_id' => $applicationId,
            'document_type' => 'identite',
            'file' => UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    private function studentApplication(): array
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $student->id,
            'inee' => 'INEE-'.Str::random(8), 'ine_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        $applicationId = (string) Str::uuid();
        $reference = 'APP-'.Str::random(8);
        DB::table('programs')->insert([
            'id' => $programId, 'name' => 'Programme étudiant', 'code' => $reference,
            'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('calls')->insert([
            'id' => $callId, 'program_id' => $programId, 'title' => 'Appel étudiant',
            'reference' => $reference, 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(),
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId,
            'applicant_id' => $student->id, 'reference' => $reference, 'status' => 'brouillon',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$student, $applicationId, $programId];
    }
}
