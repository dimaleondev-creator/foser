<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\HomeSlider;
use App\Models\News;
use App\Models\Partner;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_live_database_content(): void
    {
        $program = Program::create([
            'name' => 'Programme test',
            'code' => 'PROG-TEST',
            'type' => 'research',
            'description' => 'Programme de test',
            'status' => 'published',
        ]);

        HomeSlider::create([
            'title' => 'Bannière d’accueil test',
            'description' => 'Texte dynamique',
            'button_label' => 'Découvrir',
            'button_url' => '/calls',
            'is_active' => true,
            'sort_order' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $openCall = Call::create([
            'program_id' => $program->id,
            'title' => 'Appel test ouvert',
            'reference' => 'CALL-001',
            'description' => 'Description de l’appel',
            'opens_at' => now()->subWeek(),
            'closes_at' => now()->addWeek(),
            'status' => 'published',
        ]);

        Call::create([
            'program_id' => $program->id,
            'title' => 'Appel test clôturé',
            'reference' => 'CALL-002',
            'opens_at' => now()->subWeeks(2),
            'closes_at' => now()->subDay(),
            'status' => 'published',
        ]);

        News::create([
            'title' => 'Actualité dynamique',
            'slug' => 'actualite-dynamique',
            'excerpt' => 'Extrait d’actualité',
            'body' => 'Contenu de l’actualité',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Bannière d’accueil test')
            ->assertSee('Appel test ouvert')
            ->assertSee('Actualité dynamique')
            ->assertSee(route('calls.show', $openCall->id), false)
            ->assertSee(route('student.login', ['call_id' => $openCall->id]), false)
            ->assertDontSee('Appel test clôturé');
    }

    public function test_partners_default_to_footer_and_can_be_placed_on_homepage(): void
    {
        Partner::create([
            'name' => 'Partenaire du footer',
            'slug' => 'partenaire-footer',
            'status' => 'published',
        ]);
        Partner::create([
            'name' => 'Partenaire accueil',
            'slug' => 'partenaire-accueil',
            'status' => 'published',
            'display_location' => 'home',
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $mainEnd = strpos($html, '</main>');
        $footerStart = strpos($html, '<footer class="footer">');

        $this->assertNotFalse($mainEnd);
        $this->assertNotFalse($footerStart);
        $this->assertGreaterThan($footerStart, strpos($html, 'Partenaire du footer'));
        $this->assertLessThan($mainEnd, strpos($html, 'Partenaire accueil'));
        $this->assertSame('footer', Partner::where('slug', 'partenaire-footer')->value('display_location'));
    }

    public function test_newsletter_subscription_is_persisted(): void
    {
        $this->post('/newsletter', ['name' => 'Alice', 'email' => 'alice@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'alice@example.com',
            'status' => 'pending',
        ]);

        $subscriber = \App\Models\NewsletterSubscriber::where('email', 'alice@example.com')->firstOrFail();
        $token = 'newsletter-confirmation-token';
        $subscriber->update(['confirmation_token_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addHour()]);
        $this->get('/newsletter/confirm/'.$token)->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'alice@example.com', 'status' => 'active']);
        $this->get('/newsletter/confirm/'.$token)->assertNotFound();

        $this->post('/newsletter/unsubscribe', ['email' => 'alice@example.com'])->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'alice@example.com', 'status' => 'active']);
        $subscriber->refresh()->update(['unsubscribe_token_hash' => hash('sha256', $token), 'unsubscribe_expires_at' => now()->addHour()]);
        $this->get('/newsletter/unsubscribe/'.$token)->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'alice@example.com', 'status' => 'inactive']);
        $this->get('/newsletter/unsubscribe/'.$token)->assertNotFound();
    }

    public function test_contact_form_is_persisted(): void
    {
        $this->post('/contact', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'bob@example.com',
            'subject' => 'Question',
        ]);
    }
}
