<?php

namespace Tests\Feature;

use App\Models\CmsContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_french_content_is_rendered_on_public_page(): void
    {
        CmsContent::create(['content_key' => 'about.heading', 'locale' => 'fr', 'title' => 'Notre mission officielle', 'status' => 'published', 'published_at' => now()]);
        CmsContent::create(['content_key' => 'about.body', 'locale' => 'fr', 'body' => 'Un contenu administrable.', 'status' => 'published', 'published_at' => now()]);

        $this->get('/about')->assertOk()->assertSee('Notre mission officielle')->assertSee('Un contenu administrable.');
    }

    public function test_draft_content_is_not_rendered_publicly(): void
    {
        CmsContent::create(['content_key' => 'about.heading', 'locale' => 'fr', 'title' => 'Brouillon privé', 'status' => 'draft']);

        $this->get('/about')->assertOk()->assertDontSee('Brouillon privé');
    }
}
