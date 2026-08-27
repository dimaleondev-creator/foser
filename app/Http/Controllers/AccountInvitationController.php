<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountInvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);
        abort_unless($invitation, 404);

        return view('auth.activate-account', ['token' => $token, 'email' => $invitation->email]);
    }

    public function activate(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', 'min:8']]);
        $invitation = $this->findInvitation($token);
        abort_unless($invitation, 404);

        DB::transaction(function () use ($data, $invitation, $token): void {
            $user = User::findOrFail($invitation->user_id);
            $user->forceFill(['password' => Hash::make($data['password']), 'status' => 'active', 'email_verified_at' => now()])->save();
            DB::table('account_invitations')->where('id', $invitation->id)->update(['used_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('account.activated', User::class, (string) $user->id, ['status' => 'invited'], ['status' => 'active']);
        });

        return redirect()->route('student.login')->with('status', 'Compte activé. Vous pouvez vous connecter.');
    }

    private function findInvitation(string $token): ?object
    {
        return DB::table('account_invitations')
            ->join('users', 'users.id', '=', 'account_invitations.user_id')
            ->where('account_invitations.token_hash', hash('sha256', $token))
            ->whereNull('account_invitations.used_at')
            ->where('account_invitations.expires_at', '>', now())
            ->select('account_invitations.*', 'users.email')
            ->first();
    }
}
