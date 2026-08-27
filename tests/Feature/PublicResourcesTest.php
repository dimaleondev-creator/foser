<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\MediaAlbum;
use App\Models\ProgramFaq;
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
        Document::create(['category_id' => $category->id, 'title' => 'Guide officiel', 'description' => 'Démarches', 'document_type' => 'guide', 'disk' => 'private', 'path' => 'guide.pdf', 'visibility' => 'public', 'status' => 'published', 'published_at' => now(), 'year' => 2026]);

        $this->get('/centre-documentaire/guides?q=Démarches')->assertOk()->assertSee('Guide officiel');
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
}
