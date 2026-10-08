<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\News;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNewsAndCallsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_news_is_public_and_searchable(): void
    {
        News::create(['title' => 'Article publié', 'slug' => 'article-publie', 'excerpt' => 'Information utile', 'body' => 'Contenu', 'status' => 'published', 'published_at' => now()->subDay()]);
        News::create(['title' => 'Article brouillon', 'slug' => 'article-brouillon', 'excerpt' => 'Secret', 'body' => 'Contenu', 'status' => 'draft']);

        $this->get('/news?q=utile')->assertOk()->assertSee('Article publié')->assertDontSee('Article brouillon');
        $this->get('/news/article-publie')->assertOk()->assertSee('Contenu');
        $this->get('/news/article-brouillon')->assertNotFound();
    }

    public function test_news_published_at_current_time_is_visible_on_its_detail_page(): void
    {
        $this->travelTo(now());
        News::create([
            'title' => 'Actualité publiée maintenant',
            'slug' => 'actualite-publiee-maintenant',
            'body' => 'Contenu immédiatement public.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/news/actualite-publiee-maintenant')->assertOk()->assertSee('Contenu immédiatement public.');
    }

    public function test_internal_or_scheduled_news_never_appears_on_public_pages(): void
    {
        News::create(['title' => 'Actualité interne publiée', 'slug' => 'actualite-interne', 'body' => 'Interne', 'status' => 'published', 'visibility' => 'internal', 'published_at' => now()->subMinute()]);
        News::create(['title' => 'Actualité planifiée', 'slug' => 'actualite-planifiee', 'body' => 'Futur', 'status' => 'published', 'published_at' => now()->addDay()]);

        $this->get('/news')->assertOk()->assertDontSee('Actualité interne publiée')->assertDontSee('Actualité planifiée');
        $this->get('/news/actualite-interne')->assertNotFound();
        $this->get('/news/actualite-planifiee')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Actualité interne publiée')->assertDontSee('Actualité planifiée');
    }

    public function test_calls_search_description_and_preserve_filters_in_pagination(): void
    {
        $program = Program::create(['name' => 'Programme appels', 'code' => 'CALLS-TEST', 'type' => 'research', 'status' => 'published']);
        Call::create(['program_id' => $program->id, 'title' => 'Appel ciblé', 'reference' => 'CALL-SEARCH', 'description' => 'Innovation locale', 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published']);

        $this->get('/calls?q=Innovation&status=published&program='.$program->id)
            ->assertOk()->assertSee('Appel ciblé')->assertSee('Innovation locale');
    }
}
