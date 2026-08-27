<?php

namespace App\Services;

use App\Jobs\DeliverNotificationJob;
use App\Contracts\SmsProvider;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    public const CHANNELS = ['internal', 'email', 'sms', 'whatsapp'];

    public function notify(User $user, string $event, array $data = [], ?array $channels = null, ?string $key = null): void
    {
        $key ??= (string) ($data['_idempotency_key'] ?? $event.':'.$user->id.':'.sha1(json_encode($data)));
        unset($data['_idempotency_key']);
        foreach ($this->channels($user, $event, $channels) as $channel) {
            $deliveryId = (string) Str::uuid();
            $created = DB::table('notification_deliveries')->insertOrIgnore(['id' => $deliveryId, 'user_id' => $user->id, 'event' => $event, 'channel' => $channel, 'idempotency_key' => $key, 'status' => 'queued', 'payload' => Crypt::encryptString(json_encode($data)), 'created_at' => now(), 'updated_at' => now()]);
            if ($created) dispatch(new DeliverNotificationJob($deliveryId));
        }
    }

    public function deliver(string $deliveryId, SmsProvider $sms): void
    {
        $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->firstOrFail();
        if ($delivery->status === 'sent' || $delivery->status === 'prepared') return;
        if ($delivery->status === 'queued' && DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'queued')->update(['status' => 'sending', 'updated_at' => now()]) === 0) return;
        $data = json_decode(Crypt::decryptString($delivery->payload ?: Crypt::encryptString('{}')), true) ?: [];
        $user = User::findOrFail($delivery->user_id);
        $template = DB::table('notification_templates')->where('event', $delivery->event)->where('channel', $delivery->channel)->where('active', true)->first();
        $subject = $template?->subject ?: 'Notification FOSER';
        $body = $this->render($template?->body ?: $this->fallback($delivery->event), $data);
        DB::table('notification_deliveries')->where('id', $deliveryId)->increment('attempts');

        if ($delivery->channel === 'internal') {
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => $delivery->event, 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => json_encode(['subject' => $subject, 'message' => $body]), 'created_at' => now(), 'updated_at' => now()]);
            $status = 'sent';
        } elseif ($delivery->channel === 'email') {
            Mail::raw($body, fn ($message) => $message->to($user->email)->subject($subject));
            $status = 'sent';
        } elseif ($delivery->channel === 'sms') {
            abort_unless(config('services.sms.enabled'), 503, 'Le service SMS est désactivé.');
            $phone = DB::table('student_profiles')->where('user_id', $user->id)->value('phone');
            abort_unless($phone, 422, 'Le destinataire ne possède pas de numéro de téléphone.');
            $sms->send($phone, $body);
            $status = 'sent';
        } else {
            $status = 'prepared';
        }
        DB::table('notification_deliveries')->where('id', $deliveryId)->update(['status' => $status, 'sent_at' => now(), 'updated_at' => now()]);
    }

    public function markFailed(string $deliveryId, string $error): void
    {
        DB::table('notification_deliveries')->where('id', $deliveryId)->update(['status' => 'failed', 'last_error' => Str::limit($error, 2000), 'updated_at' => now()]);
    }

    private function channels(User $user, string $event, ?array $requested): array
    {
        $channels = $requested ? array_values(array_intersect($requested, self::CHANNELS)) : ['internal', 'email'];
        if (! config('services.sms.enabled')) $channels = array_values(array_diff($channels, ['sms']));
        $disabled = DB::table('notification_preferences')->where('user_id', $user->id)->where('event', $event)->where('enabled', false)->pluck('channel')->all();
        return array_values(array_diff($channels, $disabled));
    }

    private function render(string $body, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', fn (array $match): string => (string) data_get($data, $match[1], ''), $body) ?: $body;
    }

    private function fallback(string $event): string
    {
        return match ($event) {
            'account.created' => 'Votre compte FOSER a été créé.',
            'account.verified' => 'Votre compte FOSER est vérifié.',
            'application.submitted' => 'Votre dossier a été soumis au FOSER.',
            'application.result' => 'Une décision concernant votre dossier est disponible.',
            'message.created' => 'Vous avez reçu un nouveau message FOSER.',
            'reminder' => 'Rappel FOSER : une action est attendue sur votre espace.',
            default => 'Une nouvelle information FOSER est disponible.',
        };
    }
}
