<?php

namespace Tests\Feature;

use App\Models\CmsContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_french_is_the_default_locale(): void
    {
        $this->get('/about')->assertOk()->assertSee('Nous contacter');
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_locale_switch_is_persistent_with_a_cookie(): void
    {
        $this->get('/language/en')->assertRedirect()->assertCookie('locale', 'en');
        $this->withCookie('locale', 'en')->get('/about')->assertOk()->assertSee('<html lang="en">', false)->assertSee('>Home<', false);
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->get('/language/mo')->assertNotFound();
    }

    public function test_english_cms_content_overrides_the_french_fallback(): void
    {
        CmsContent::create(['content_key' => 'about.heading', 'locale' => 'fr', 'title' => 'Mission française', 'status' => 'published', 'published_at' => now()]);
        CmsContent::create(['content_key' => 'about.heading', 'locale' => 'en', 'title' => 'English mission', 'status' => 'published', 'published_at' => now()]);

        $this->withCookie('locale', 'en')->get('/about')->assertOk()->assertSee('English mission')->assertDontSee('Mission française');
    }
}
