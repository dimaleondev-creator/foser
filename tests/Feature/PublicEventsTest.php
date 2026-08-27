<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_agenda_lists_published_events_and_hides_drafts(): void
    {
        $published = Event::create([
            'title' => 'Forum de la recherche',
            'slug' => 'forum-recherche',
            'description' => 'Rencontre scientifique annuelle.',
            'category' => 'Recherche',
            'starts_at' => now()->addDays(5),
            'status' => 'published',
        ]);
        Event::create([
            'title' => 'Événement interne',
            'slug' => 'evenement-interne',
            'starts_at' => now()->addDays(3),
            'status' => 'draft',
        ]);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee('Événement interne');
    }

    public function test_public_agenda_filters_by_search_and_shows_published_detail(): void
    {
        $event = Event::create([
            'title' => 'Journée innovation',
            'slug' => 'journee-innovation',
            'description' => 'Ateliers pour les porteurs de projets.',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);
        Event::create([
            'title' => 'Colloque éducation',
            'slug' => 'colloque-education',
            'starts_at' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->get(route('events.index', ['q' => 'innovation']))
            ->assertOk()
            ->assertSee($event->title)
            ->assertDontSee('Colloque éducation');
        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee($event->description);
    }

    public function test_draft_event_detail_is_not_public(): void
    {
        $event = Event::create([
            'title' => 'Réunion confidentielle',
            'slug' => 'reunion-confidentielle',
            'starts_at' => now()->addDay(),
            'status' => 'draft',
        ]);

        $this->get(route('events.show', $event->slug))->assertNotFound();
    }
}
