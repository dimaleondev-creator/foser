<?php

namespace App\Services;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class NewsletterCampaignService
{
    public function send(NewsletterCampaign $campaign): void
    {
        Gate::authorize('content.publish');
        abort_unless($campaign->status === 'draft', 422, 'Cette campagne ne peut plus être envoyée.');
        abort_if(blank($campaign->subject) || blank($campaign->body), 422, 'L’objet et le contenu sont requis.');

        $recipients = NewsletterSubscriber::query()
            ->where('status', 'active')
            ->whereNotNull('confirmed_at')
            ->whereNull('deleted_at')
            ->get(['id', 'email']);

        DB::transaction(function () use ($campaign, $recipients): void {
            $campaign->forceFill([
                'status' => 'sending', 'created_by' => $campaign->created_by ?: Auth::id(),
                'recipient_count' => $recipients->count(), 'sent_count' => 0, 'failed_count' => 0,
            ])->save();
        });

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $recipient) {
            try {
                Mail::raw($campaign->body, fn ($message) => $message->to($recipient->email)->subject($campaign->subject));
                $sent++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $campaign->forceFill([
            'status' => $failed === 0 ? 'sent' : ($sent > 0 ? 'partially_sent' : 'failed'),
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
        ])->save();

        app(AuditLogger::class)->record('communication.newsletter_campaign.sent', 'newsletter_campaigns', (string) $campaign->id, [], [
            'status' => $campaign->status, 'recipient_count' => $campaign->recipient_count,
            'sent_count' => $sent, 'failed_count' => $failed,
        ]);
    }
}
