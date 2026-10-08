<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\User;
use App\Services\DocumentFileService;
use Database\Seeders\DocumentCategoriesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

        $response->assertOk()->assertDownload('loi-foser.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('downloads', ['document_id' => $document->id, 'user_id' => $user->id]);
    }

    public function test_official_document_categories_are_seeded_for_administration(): void
    {
        $this->seed(DocumentCategoriesSeeder::class);

        $this->assertDatabaseCount('document_categories', 13);
        $this->assertDatabaseHas('document_categories', ['slug' => 'lois', 'name' => 'Lois']);
        $this->assertDatabaseHas('document_categories', ['slug' => 'textes-reglementaires', 'name' => 'Textes réglementaires']);
        $this->assertDatabaseHas('document_categories', ['slug' => 'rapports-annuels', 'name' => 'Rapports annuels']);
        $this->assertDatabaseHas('document_categories', ['slug' => 'autres-documents-officiels', 'name' => 'Autres documents officiels']);
    }

    public function test_user_cannot_download_another_users_private_published_document(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('student-documents/private.pdf', 'private content');
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo(Permission::findOrCreate('documents.view', 'web'));
        $document = Document::create([
            'title' => 'Pièce privée', 'document_type' => 'justificatif', 'disk' => 'private',
            'path' => 'student-documents/private.pdf', 'language' => 'fr', 'status' => 'published',
            'visibility' => 'private', 'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($otherUser)->getJson('/api/v1/documents/'.$document->id.'/download')->assertNotFound();
        $this->assertDatabaseMissing('downloads', ['document_id' => $document->id, 'user_id' => $otherUser->id]);
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

    public function test_replacing_a_file_keeps_both_versions_and_refreshes_file_metadata(): void
    {
        Storage::fake('private');
        Storage::fake('local');
        Storage::disk('private')->put('institutional/first.pdf', 'first version');
        Storage::disk('local')->put('official-documents/second.pdf', 'second version');
        $user = User::factory()->create();
        $document = Document::create([
            'title' => 'Rapport annuel', 'document_type' => 'rapport', 'disk' => 'private',
            'path' => 'institutional/first.pdf', 'language' => 'fr', 'status' => 'draft',
            'visibility' => 'private', 'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user);
        $document->update(['path' => 'official-documents/second.pdf']);

        $this->assertDatabaseCount('document_versions', 2);
        $this->assertSame(['institutional/first.pdf', 'official-documents/second.pdf'], $document->versions()->orderBy('version')->pluck('path')->all());
        $this->assertSame(['private', 'local'], $document->versions()->orderBy('version')->pluck('disk')->all());
        $this->assertSame(hash('sha256', 'second version'), $document->refresh()->checksum);
        $this->assertSame(strlen('second version'), $document->size);
        $audit = \Illuminate\Support\Facades\DB::table('audit_logs')->where('event', 'document.updated')->where('auditable_id', $document->id)->first();
        $this->assertSame('institutional/first.pdf', json_decode($audit->old_values, true)['path']);
        $this->assertSame('official-documents/second.pdf', json_decode($audit->new_values, true)['path']);
    }

    public function test_admin_can_upload_a_private_document_with_a_generated_filename(): void
    {
        Storage::fake('local');
        $this->seed(DocumentCategoriesSeeder::class);
        $category = DocumentCategory::query()->where('slug', 'guides')->firstOrFail();
        $staff = User::factory()->create(['account_type' => 'agent_dossier', 'status' => 'active']);
        $staff->givePermissionTo([
            Permission::findOrCreate('documents.view', 'web'),
            Permission::findOrCreate('documents.upload', 'web'),
        ]);
        $this->actingAs($staff);

        Livewire::test(ManageDocuments::class)
            ->callAction(CreateAction::class, data: [
                'title' => 'Guide de dépôt',
                'description' => 'Procédure de dépôt.',
                'category_id' => $category->id,
                'document_type' => 'guide',
                'language' => 'fr',
                'status' => 'draft',
                'visibility' => 'private',
                'path' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoActionErrors();

        $document = Document::query()->where('title', 'Guide de dépôt')->firstOrFail();
        $this->assertSame('local', $document->disk);
        $this->assertStringStartsWith('official-documents/', $document->path);
        $this->assertStringNotContainsString('guide.pdf', $document->path);
        $this->assertTrue(Storage::disk('local')->exists($document->path));
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 1]);
    }

    public function test_admin_upload_rejects_executable_file_types(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['account_type' => 'agent_dossier', 'status' => 'active']);
        $staff->givePermissionTo([
            Permission::findOrCreate('documents.view', 'web'),
            Permission::findOrCreate('documents.upload', 'web'),
        ]);
        $this->actingAs($staff);

        Livewire::test(ManageDocuments::class)
            ->callAction(CreateAction::class, data: [
                'title' => 'Fichier interdit',
                'document_type' => 'autre',
                'language' => 'fr',
                'status' => 'draft',
                'visibility' => 'private',
                'path' => UploadedFile::fake()->create('script.php', 20, 'application/x-php'),
            ])
            ->assertHasActionErrors(['path']);

        $this->assertDatabaseMissing('documents', ['title' => 'Fichier interdit']);
    }

    public function test_public_pdf_preview_is_inline_and_private_documents_remain_hidden(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('official/report.pdf', '%PDF-1.7 sample');
        $public = Document::create([
            'title' => 'Rapport annuel 2026', 'document_type' => 'rapport', 'disk' => 'private',
            'path' => 'official/report.pdf', 'mime_type' => 'application/pdf', 'language' => 'fr',
            'status' => 'published', 'visibility' => 'public', 'published_at' => now(),
        ]);
        $private = Document::create([
            'title' => 'Rapport réservé', 'document_type' => 'rapport', 'disk' => 'private',
            'path' => 'official/report.pdf', 'mime_type' => 'application/pdf', 'language' => 'fr',
            'status' => 'published', 'visibility' => 'private', 'published_at' => now(),
        ]);

        $this->get(route('documents.preview', $public))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('documents.download', $public))->assertOk();
        $this->get(route('documents.preview', $private))->assertNotFound();
        $this->get(route('documents.download', $private))->assertNotFound();
        $this->assertDatabaseHas('downloads', ['document_id' => $public->id, 'user_id' => null]);
    }

    public function test_private_document_saved_on_public_disk_is_moved_out_of_public_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('private-documents/secret.pdf', 'confidential content');

        $document = Document::create([
            'title' => 'Document confidentiel',
            'document_type' => 'rapport',
            'disk' => 'public',
            'path' => 'private-documents/secret.pdf',
            'status' => 'draft',
        ]);

        $this->assertSame('local', $document->disk);
        $this->assertSame('private', $document->visibility);
        $this->assertStringStartsWith('official-documents/', $document->path);
        $this->assertFalse(Storage::disk('public')->exists('private-documents/secret.pdf'));
        $this->assertTrue(Storage::disk('local')->exists($document->path));
        $this->get('/storage/private-documents/secret.pdf')->assertNotFound();
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_making_a_public_document_private_moves_its_historical_versions_too(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('institutional/report.pdf', 'public version');
        $document = Document::create([
            'title' => 'Rapport rendu privé',
            'document_type' => 'rapport',
            'disk' => 'public',
            'path' => 'institutional/report.pdf',
            'visibility' => 'public',
            'status' => 'published',
        ]);
        $staff = User::factory()->create();
        $staff->givePermissionTo(Permission::findOrCreate('documents.validate', 'web'));

        $this->actingAs($staff);
        $document->update(['visibility' => 'private']);

        $version = $document->versions()->where('version', 1)->firstOrFail();
        $this->assertSame('local', $version->disk);
        $this->assertTrue(Storage::disk('local')->exists($version->path));
        $this->assertFalse(Storage::disk('public')->exists('institutional/report.pdf'));
        $this->assertSame('local', $document->refresh()->disk);
        $this->get('/storage/institutional/report.pdf')->assertNotFound();
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_unauthorized_visibility_change_does_not_move_the_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('institutional/public.pdf', 'public content');
        $document = Document::create([
            'title' => 'Document public',
            'document_type' => 'rapport',
            'disk' => 'public',
            'path' => 'institutional/public.pdf',
            'visibility' => 'public',
            'status' => 'published',
        ]);
        $privatePath = $document->path;
        $this->assertSame('local', $document->disk);
        $this->assertFalse(Storage::disk('public')->exists('institutional/public.pdf'));
        $this->assertTrue(Storage::disk('local')->exists($privatePath));
        $editor = User::factory()->create();
        $editor->givePermissionTo(Permission::findOrCreate('documents.update', 'web'));

        $this->actingAs($editor);
        try {
            $document->update(['visibility' => 'private']);
            $this->fail('Changing visibility requires documents.validate.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertTrue(Storage::disk('local')->exists($privatePath));
            $this->assertSame('public', $document->fresh()->visibility);
        }
    }

    public function test_catalog_search_matches_keywords_and_category_name(): void
    {
        $category = DocumentCategory::create(['name' => 'Textes réglementaires', 'slug' => 'textes-reglementaires']);
        $document = Document::create([
            'category_id' => $category->id,
            'title' => 'Publication officielle',
            'description' => 'Texte de référence.',
            'keywords' => 'cadre juridique, conformité',
            'document_type' => 'texte_reglementaire',
            'disk' => 'private',
            'path' => 'official/legal.pdf',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('documents.index', ['q' => 'conformité']))->assertOk()->assertSee($document->title);
        $this->get(route('documents.index', ['q' => 'réglementaires']))->assertOk()->assertSee($document->title);
        $this->getJson('/api/v1/public/documents?q=juridique')->assertOk()->assertJsonFragment(['title' => $document->title]);
    }

    public function test_permanent_document_deletion_requires_permission_and_removes_all_versions(): void
    {
        Storage::fake('private');
        Storage::fake('local');
        Storage::disk('private')->put('institutional/old.pdf', 'old');
        Storage::disk('local')->put('official-documents/current.pdf', 'current');
        $document = Document::create([
            'title' => 'Document à supprimer', 'document_type' => 'guide', 'disk' => 'private',
            'path' => 'institutional/old.pdf', 'language' => 'fr', 'status' => 'draft',
        ]);
        $document->update(['path' => 'official-documents/current.pdf']);
        $unauthorized = User::factory()->create();
        $authorized = User::factory()->create();
        $authorized->givePermissionTo(Permission::findOrCreate('documents.delete', 'web'));

        $this->actingAs($unauthorized);
        try {
            app(DocumentFileService::class)->deletePermanently($document);
            $this->fail('Deletion without documents.delete must be denied.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertTrue(Storage::disk('local')->exists('official-documents/current.pdf'));
        }

        $this->actingAs($authorized);
        app(DocumentFileService::class)->deletePermanently($document);

        $this->assertFalse(Storage::disk('private')->exists('institutional/old.pdf'));
        $this->assertFalse(Storage::disk('local')->exists('official-documents/current.pdf'));
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('document_versions', ['document_id' => $document->id]);
    }

    public function test_authorized_staff_can_download_a_historical_version(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('official-documents/old.pdf', 'historical content');
        $document = Document::create([
            'title' => 'Rapport institutionnel', 'document_type' => 'rapport', 'disk' => 'local',
            'path' => 'official-documents/old.pdf', 'language' => 'fr', 'status' => 'draft',
        ]);
        $version = $document->versions()->firstOrFail();
        $staff = User::factory()->create();
        $staff->givePermissionTo(Permission::findOrCreate('documents.validate', 'web'));

        $reader = User::factory()->create(['account_type' => 'etudiant']);
        $reader->givePermissionTo(Permission::findOrCreate('documents.view', 'web'));
        $this->actingAs($reader)->get(route('admin.documents.versions.download', [$document, $version]))->assertForbidden();
        $this->actingAs($staff)
            ->get(route('admin.documents.versions.download', [$document, $version]))
            ->assertOk()
            ->assertDownload('rapport-institutionnel-v1.pdf');
    }

    public function test_publishing_requires_document_validation_permission(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(Permission::findOrCreate('documents.upload', 'web'));
        $this->actingAs($editor);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        Document::create([
            'title' => 'Publication non autorisée', 'document_type' => 'guide', 'disk' => 'local',
            'path' => 'official-documents/unapproved.pdf', 'language' => 'fr', 'status' => 'published', 'visibility' => 'public',
        ]);
    }
}
