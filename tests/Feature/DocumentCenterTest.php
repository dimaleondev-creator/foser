<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_published_document_download_is_secure_and_counted(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('institutional/law.pdf', 'content');
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->givePermissionTo(Permission::findOrCreate('documents.view', 'web'));
        $document = Document::create([
            'title' => 'Loi FOSER', 'document_type' => 'loi', 'disk' => 'private',
            'path' => 'institutional/law.pdf', 'language' => 'fr', 'status' => 'published',
            'year' => 2026, 'published_at' => now(), 'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/api/v1/documents/' . $document->id . '/download');

        $response->assertOk()->assertDownload('Loi FOSER.pdf');
        $this->assertDatabaseHas('downloads', ['document_id' => $document->id, 'user_id' => $user->id]);
    }

    public function test_draft_document_cannot_be_downloaded(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('institutional/draft.pdf', 'content');
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('documents.view', 'web'));
        $document = Document::create([
            'title' => 'Brouillon', 'document_type' => 'autre', 'disk' => 'private',
            'path' => 'institutional/draft.pdf', 'language' => 'fr', 'status' => 'draft',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson('/api/v1/documents/' . $document->id . '/download')->assertNotFound();
        $this->assertDatabaseCount('downloads', 0);
    }

    public function test_unpublished_document_cannot_be_read_by_uuid(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('documents.view', 'web'));
        $document = Document::create([
            'title' => 'Pièce privée étudiant', 'document_type' => 'justificatif', 'disk' => 'local',
            'path' => 'student-documents/private.pdf', 'language' => 'fr', 'status' => 'draft',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson('/api/v1/documents/' . $document->id)->assertNotFound();
    }
}
