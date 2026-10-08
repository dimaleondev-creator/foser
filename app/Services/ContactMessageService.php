<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class ContactMessageService
{
    public function reply(ContactMessage $contact, string $body): ContactMessageReply
    {
        Gate::authorize('content.update');
        $reply = ContactMessageReply::create([
            'contact_message_id' => $contact->id,
            'sender_id' => Auth::id(),
            'body' => trim($body),
            'delivery_status' => 'pending',
        ]);

        try {
            Mail::raw($reply->body, fn ($message) => $message->to($contact->email)->subject('Réponse FOSER : '.$contact->subject));
            $reply->forceFill(['delivery_status' => 'sent', 'sent_at' => now()])->save();
            $contact->forceFill(['status' => 'replied'])->save();
        } catch (\Throwable) {
            $reply->forceFill(['delivery_status' => 'failed'])->save();
        }

        app(AuditLogger::class)->record('communication.contact.replied', 'contact_messages', (string) $contact->id, [], [
            'reply_id' => $reply->id, 'delivery_status' => $reply->delivery_status, 'performed_by' => Auth::id(),
        ]);

        return $reply;
    }

    public function setStatus(ContactMessage $contact, string $status): void
    {
        Gate::authorize('content.update');
        abort_unless(in_array($status, ['new', 'read', 'replied', 'closed'], true), 422);
        $old = $contact->status;
        $contact->forceFill(['status' => $status, 'read_at' => $contact->read_at ?: ($status === 'read' ? now() : null), 'closed_at' => $status === 'closed' ? now() : null])->save();
        app(AuditLogger::class)->record('communication.contact.status_changed', 'contact_messages', (string) $contact->id, ['status' => $old], ['status' => $status, 'performed_by' => Auth::id()]);
    }
}
