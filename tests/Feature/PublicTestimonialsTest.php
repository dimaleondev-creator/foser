<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTestimonialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_consented_testimonials_are_public(): void
    {
        $published = Testimonial::create(['first_name' => 'Awa', 'last_name' => 'K.', 'body' => 'Un parcours transformé.', 'status' => 'published', 'consent_given' => true, 'published_at' => now()]);
        Testimonial::create(['first_name' => 'Paul', 'last_name' => 'D.', 'body' => 'Brouillon privé.', 'status' => 'draft', 'consent_given' => true]);
        Testimonial::create(['first_name' => 'Moussa', 'last_name' => 'T.', 'body' => 'Sans consentement.', 'status' => 'published', 'consent_given' => false, 'published_at' => now()]);

        $this->get(route('testimonials.index'))->assertOk()->assertSee($published->body)->assertDontSee('Brouillon privé')->assertDontSee('Sans consentement');
    }
}
