<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\MediaAlbum;
use App\Models\ProgramFaq;
use App\Models\Media;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_center_searches_published_documents_by_category(): void
    {
        $category = DocumentCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        Document::create([
            'category_id' => $category->id, 'title' => 'Guide officiel', 'description' => 'Démarches',
            'document_type' => 'guide', 'reference' => 'FOSER-GUI-2026-01', 'keywords' => 'candidature, dépôt', 'disk' => 'private',
            'path' => 'guide.pdf', 'mime_type' => 'application/pdf', 'visibility' => 'public',
            'status' => 'published', 'published_at' => now(), 'year' => 2026, 'language' => 'fr',
        ]);

        $this->get('/centre-documentaire/guides?q=FOSER-GUI-2026-01&language=fr&type=guide')
            ->assertOk()
            ->assertSee('Guide officiel')
            ->assertSee('FOSER-GUI-2026-01')
            ->assertSee('Prévisualiser le PDF')
            ->assertSee('0 téléchargement(s)');

        $this->get('/centre-documentaire?q=dépôt')->assertOk()->assertSee('Guide officiel');
    }

    public function test_scheduled_public_documents_are_hidden_until_the_publication_date(): void
    {
        $document = Document::create([
            'title' => 'Rapport à venir', 'document_type' => 'rapport', 'disk' => 'local',
            'path' => 'official-documents/scheduled.pdf', 'mime_type' => 'application/pdf',
            'visibility' => 'public', 'status' => 'published', 'published_at' => now()->addDay(),
        ]);

        $this->get(route('documents.index'))->assertOk()->assertDontSee($document->title);
        $this->get(route('documents.preview', $document))->assertNotFound();
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_private_or_draft_documents_are_not_downloadable(): void
    {
        $document = Document::create(['title' => 'Document privé', 'document_type' => 'guide', 'disk' => 'private', 'path' => 'private.pdf', 'visibility' => 'private', 'status' => 'draft']);

        $this->get('/documents/'.$document->id.'/telecharger')->assertNotFound();
    }

    public function test_faq_and_contact_use_published_and_public_data(): void
    {
        ProgramFaq::create(['category' => 'general', 'question' => 'Comment contacter le FOSER ?', 'answer' => 'Par email.', 'status' => 'published', 'sort_order' => 1]);
        SystemSetting::create(['key' => 'contact_email', 'value' => 'contact@foser.test', 'is_public' => true]);

        $this->get('/faq?q=contacter')->assertOk()->assertSee('Comment contacter le FOSER ?');
        $this->get('/contact')->assertOk()->assertSee('contact@foser.test')->assertSee('mailto:contact@foser.test');
    }

    public function test_published_album_is_visible(): void
    {
        $album = MediaAlbum::create(['name' => 'Album FOSER', 'slug' => 'album-foser', 'status' => 'published']);

        $this->get('/mediatheque')->assertOk()->assertSee('Album FOSER');
        $this->get('/mediatheque/albums/'.$album->slug)->assertOk()->assertSee('Album FOSER');
    }

    public function test_published_external_video_is_listed_without_requiring_a_video_metadata_row(): void
    {
        Media::create(['title' => 'Vidéo institutionnelle', 'media_type' => 'video', 'disk' => 'public', 'path' => 'https://video.example.test/watch/1', 'external_url' => 'https://video.example.test/watch/1', 'status' => 'published']);

        $this->get('/mediatheque/videos')->assertOk()->assertSee('Vidéo institutionnelle')->assertSee('https://video.example.test/watch/1');
    }
}
