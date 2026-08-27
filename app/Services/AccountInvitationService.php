<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AccountInvitationService
{
    public function invite(User $user, ?User $invitedBy = null): string
    {
        $plainToken = Str::random(64);

        DB::table('account_invitations')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
        DB::table('account_invitations')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'invited_by' => $invitedBy?->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(48),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::raw('Activez votre compte FOSER ici : '.route('invitation.show', ['token' => $plainToken])."\n\nCe lien expire dans 48 heures et ne peut être utilisé qu'une seule fois.", function ($message) use ($user): void {
                $message->to($user->email)->subject('Invitation à rejoindre FOSER');
            });
        } catch (TransportExceptionInterface) {
        }

        app(AuditLogger::class)->record('account.invited', User::class, (string) $user->id, [], ['account_type' => $user->account_type]);

        return $plainToken;
    }
}
