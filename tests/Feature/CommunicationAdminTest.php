<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\CmsContent;
use App\Models\Event;
use App\Models\News;
use App\Models\Partner;
use App\Models\PressRelease;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Testimonial;
use App\Models\User;
use App\Filament\Pages\CommunicationPage;
use App\Services\ContactMessageService;
use App\Services\NewsletterCampaignService;
use App\Services\NewsPublicationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommunicationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_contact_admin_actions_require_permissions_and_keep_reply_history(): void
    {
        $staff = User::factory()->create(['account_type' => 'agent_communication', 'status' => 'active']);
        $staff->assignRole('agent_communication');
        $contact = ContactMessage::create(['name' => 'Public User', 'email' => 'visitor@example.test', 'subject' => 'Question publique', 'message' => 'Demande de renseignements', 'status' => 'new']);
        $service = app(ContactMessageService::class);

        $this->actingAs($staff)->get('/admin/contact-messages')->assertOk()->assertSee('Question publique');
        $service->setStatus($contact, 'read');
        $this->assertDatabaseHas('contact_messages', ['id' => $contact->id, 'status' => 'read']);
        $service->reply($contact, 'Réponse de l’équipe');
        $this->assertDatabaseHas('contact_message_replies', ['contact_message_id' => $contact->id, 'sender_id' => $staff->id, 'body' => 'Réponse de l’équipe', 'delivery_status' => 'sent']);
        $this->assertDatabaseHas('contact_messages', ['id' => $contact->id, 'status' => 'replied']);
        $service->setStatus($contact, 'closed');
        $this->assertDatabaseHas('contact_messages', ['id' => $contact->id, 'status' => 'closed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'communication.contact.replied', 'auditable_id' => $contact->id, 'user_id' => $staff->id]);

        $reader = User::factory()->create(['account_type' => 'gestionnaire', 'status' => 'active']);
        $reader->assignRole('gestionnaire');
        $reader->revokePermissionTo('content.update');
        $this->actingAs($reader);
        $this->expectException(AuthorizationException::class);
        $service->reply($contact, 'Interdit');
    }

    public function test_newsletter_campaign_sends_only_to_confirmed_active_subscribers_once_and_reports_results(): void
    {
        Mail::shouldReceive('raw')->once();
        $publisher = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $publisher->assignRole('admin');
        $confirmed = NewsletterSubscriber::create(['email' => 'confirmed@example.test', 'status' => 'active', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'unconfirmed@example.test', 'status' => 'pending']);
        NewsletterSubscriber::create(['email' => 'unsubscribed@example.test', 'status' => 'inactive', 'confirmed_at' => now()]);
        $campaign = NewsletterCampaign::create(['title' => 'Campagne test', 'subject' => 'Actualités FOSER', 'body' => 'Information confirmée', 'status' => 'draft']);

        $this->actingAs($publisher)->get('/admin/newsletter-campaigns')->assertOk()->assertSee('Campagne test');
        app(NewsletterCampaignService::class)->send($campaign);

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        try {
            app(NewsletterCampaignService::class)->send($campaign);
            $this->fail('A sent campaign must not be sent a second time.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'communication.newsletter_campaign.sent', 'auditable_id' => $campaign->id]);
    }

    public function test_contact_reply_delivery_failure_is_recorded_without_losing_the_reply(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $staff = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $staff->assignRole('admin');
        $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.test', 'subject' => 'Question', 'message' => 'Bonjour', 'status' => 'new']);

        $this->actingAs($staff);
        $reply = app(ContactMessageService::class)->reply($contact, 'Réponse à conserver');

        $this->assertSame('failed', $reply->delivery_status);
        $this->assertDatabaseHas('contact_message_replies', ['id' => $reply->id, 'body' => 'Réponse à conserver', 'delivery_status' => 'failed']);
    }

    public function test_content_status_cannot_be_published_without_publish_permission(): void
    {
        $news = News::create(['title' => 'Brouillon', 'slug' => 'brouillon', 'body' => 'Texte', 'status' => 'draft']);
        $editor = User::factory()->create(['account_type' => 'gestionnaire', 'status' => 'active']);
        $editor->givePermissionTo(['content.view', 'content.create', 'content.update']);

        $this->actingAs($editor);
        $this->expectException(AuthorizationException::class);

        $news->update(['status' => 'published']);
    }

    public function test_content_status_cannot_be_unpublished_without_publish_permission(): void
    {
        $news = News::create(['title' => 'Publié', 'slug' => 'publie-sans-permission', 'body' => 'Texte', 'status' => 'published']);
        $editor = User::factory()->create(['account_type' => 'gestionnaire', 'status' => 'active']);
        $editor->givePermissionTo(['content.view', 'content.update']);

        $this->actingAs($editor);
        $this->expectException(AuthorizationException::class);

        $news->update(['status' => 'draft']);
    }

    public function test_content_publication_audit_keeps_old_and_new_values(): void
    {
        $publisher = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $publisher->assignRole('admin');
        $news = News::create(['title' => 'Brouillon', 'slug' => 'brouillon-audite', 'body' => 'Texte', 'status' => 'draft']);

        $this->actingAs($publisher);
        app(NewsPublicationService::class)->publish($news, $publisher);

        $audit = DB::table('audit_logs')->where('event', 'communication.content.updated')->where('auditable_id', $news->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('draft', json_decode($audit->old_values, true)['status']);
        $this->assertSame('published', json_decode($audit->new_values, true)['status']);
    }

    public function test_communication_admin_lists_render_their_managed_records(): void
    {
        $event = Event::create(['title' => 'Événement administrable', 'slug' => 'evenement-administrable', 'starts_at' => now()->addDay(), 'status' => 'draft']);
        $partner = Partner::create(['name' => 'Partenaire administrable', 'slug' => 'partenaire-administrable', 'status' => 'draft']);
        $testimonial = Testimonial::create(['first_name' => 'Awa', 'last_name' => 'Diallo', 'body' => 'Témoignage administrable', 'status' => 'draft']);
        $release = PressRelease::create(['title' => 'Communiqué administrable', 'slug' => 'communique-administrable', 'body' => str_repeat('Texte long. ', 40), 'status' => 'draft']);
        NewsletterSubscriber::create(['email' => 'gestion@example.test', 'status' => 'pending']);
        CmsContent::create(['content_key' => 'admin.communication.test', 'locale' => 'fr', 'title' => 'Page institutionnelle administrable', 'status' => 'draft']);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $this->actingAs($admin);
        $this->get('/admin/events')->assertOk()->assertSee($event->title);
        $this->get('/admin/partners')->assertOk()->assertSee($partner->name);
        $this->get('/admin/testimonials')->assertOk()->assertSee('Awa Diallo');
        $this->get('/admin/press-releases')->assertOk()->assertSee($release->title);
        $this->get('/admin/newsletter-subscribers')->assertOk()->assertSee('gestion@example.test');
        $this->get('/admin/cms-contents')->assertOk()->assertSee('Page institutionnelle administrable');
    }

    public function test_communication_hub_links_every_requested_module_to_a_management_screen(): void
    {
        $page = new CommunicationPage;

        foreach ($page->getModuleItems() as $item) {
            $this->assertNotNull($page->getModuleItemUrl($item), "No management route configured for {$item}.");
        }
    }
}