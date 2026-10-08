<?php

namespace App\Http\Controllers;

use App\Jobs\SendIneRecoveryCode;
use App\Jobs\SendIneLoginCode;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IneController extends Controller
{
    public function index(Request $request, string $mode = 'home'): View
    {
        abort_unless(in_array($mode, ['home', 'declare', 'recover'], true), 404);
        $profile = $request->user() && $request->user()->account_type === 'etudiant'
            ? DB::table('student_profiles')->where('user_id', $request->user()->id)->first()
            : null;
        $pendingDeclaration = $request->session()->get('ine.declaration.pending');
        $pendingUserId = $pendingDeclaration['user_id'] ?? null;
        if (($pendingUserId !== null && (int) $pendingUserId !== (int) $request->user()?->id) || ($pendingDeclaration['expires_at'] ?? 0) < now()->timestamp) {
            $pendingDeclaration = null;
        }
        $recoveredIne = null;
        $verifiedRecovery = $request->session()->get('ine.recovery.verified');
        if (($verifiedRecovery['expires_at'] ?? 0) >= now()->timestamp) {
            $recoveredIne = DB::table('student_profiles')->where('user_id', $verifiedRecovery['user_id'])->where('ine_status', 'verified')->value('inee');
        }

        return view('ine.index', [
            'mode' => $mode,
            'profile' => $profile,
            'pendingDeclaration' => $pendingDeclaration,
            'recoveredIne' => $recoveredIne,
            'recoverySearched' => $request->session()->get('ine.recovery.searched', false),
            'otpSent' => $request->session()->get('ine.recovery.otp_sent', false),
        ]);
    }

    public function verifyDeclaration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:120'],
            'inee' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9-]+\z/'],
        ]);
        $user = $request->user();
        $student = $user instanceof User && $user->account_type === 'etudiant' ? $user : null;
        $profile = $student ? DB::table('student_profiles')->where('user_id', $student->id)->firstOrFail() : null;
        if ($student) {
            $this->assertNameMatches($student->name, $data['first_name'], $data['last_name']);
        }

        if ($profile?->ine_status === 'verified') {
            if ($this->normalize($profile->inee) === $this->normalize($data['inee'])) {
                return redirect()->route('ine.declare')->with('status', 'Votre INE est déjà vérifié par le FOSER.');
            }
            throw ValidationException::withMessages(['inee' => 'Votre INE vérifié ne peut pas être remplacé depuis cet espace. Contactez le FOSER.']);
        }
        if ($profile?->ine_status === 'suspended') {
            throw ValidationException::withMessages(['inee' => 'L’association de votre INE est suspendue. Contactez le FOSER.']);
        }

        $existing = DB::table('student_profiles')->whereRaw('LOWER(inee) = ?', [Str::lower($data['inee'])])->first();
        if ($existing && (! $student || (int) $existing->user_id !== (int) $student->id)) {
            throw ValidationException::withMessages(['inee' => 'Cet INE est déjà associé à un compte étudiant.']);
        }

        $request->session()->put('ine.declaration.pending', [
            'user_id' => $student?->id,
            'profile_id' => $profile?->id,
            'last_name' => trim($data['last_name']),
            'first_name' => trim($data['first_name']),
            'inee' => strtoupper($data['inee']),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
        app(AuditLogger::class)->record(
            'ine.declaration.checked',
            $profile ? 'student_profiles' : 'ine_declaration',
            $profile ? (string) $profile->id : null,
            [],
            ['status' => 'pending'],
        );

        return redirect()->route('ine.declare');
    }

    public function confirmDeclaration(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('ine.declaration.pending');
        $pendingUserId = $pending['user_id'] ?? null;
        abort_unless(($pendingUserId === null || (int) $pendingUserId === (int) $request->user()->id) && ($pending['expires_at'] ?? 0) >= now()->timestamp, 419);
        $this->assertNameMatches($request->user()->name, $pending['first_name'], $pending['last_name']);
        $loginCode = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($request, $pending, $loginCode): void {
            $profile = DB::table('student_profiles')->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($profile->ine_status === 'suspended' || ($profile->ine_status === 'verified' && $this->normalize($profile->inee) !== $this->normalize($pending['inee']))) {
                throw ValidationException::withMessages(['inee' => 'Cette association ne peut pas être modifiée depuis cet espace. Contactez le FOSER.']);
            }
            $taken = DB::table('student_profiles')->whereRaw('LOWER(inee) = ?', [Str::lower($pending['inee'])])->where('user_id', '!=', $request->user()->id)->exists();
            if ($taken) {
                throw ValidationException::withMessages(['inee' => 'Cet INE est déjà associé à un compte étudiant.']);
            }

            DB::table('student_profiles')->where('id', $profile->id)->update([
                'first_name' => $pending['first_name'],
                'last_name' => $pending['last_name'],
                'inee' => $pending['inee'],
                'ine_status' => 'pending',
                'ine_verified_at' => null,
                'ine_verified_by' => null,
                'ine_login_code_hash' => Hash::make($loginCode),
                'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('ine.declared', 'student_profiles', (string) $profile->id, ['ine_status' => $profile->ine_status ?? null], ['ine_status' => 'pending']);
            app(AuditLogger::class)->record('ine.login_code_issued', 'student_profiles', (string) $profile->id, [], ['code_length' => 10]);
        });

        SendIneLoginCode::dispatch($request->user()->id, Crypt::encryptString($loginCode));
        $request->session()->forget('ine.declaration.pending');

            return redirect()->route('ine.declare')->with('status', 'Votre code de connexion à 10 chiffres a été envoyé par email. Vous pouvez déjà vous connecter avec votre INE et ce code; le contrôle administratif du FOSER suivra.');
    }

    public function search(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'birth_place' => ['required', 'string', 'max:120'],
        ]);
        $match = DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->whereDate('student_profiles.date_of_birth', $data['date_of_birth'])
            ->whereNotNull('student_profiles.inee')
            ->where('student_profiles.ine_status', 'verified')
            ->select('student_profiles.id', 'student_profiles.user_id', 'student_profiles.inee', 'student_profiles.birth_place', 'student_profiles.first_name', 'student_profiles.last_name', 'users.name')
            ->get()
            ->first(fn (object $record): bool => $this->identityMatches($record, $data));

        $token = Str::random(64);
        if ($match) {
            Cache::put('ine:recovery:match:'.$token, [
                'profile_id' => $match->id,
                'user_id' => $match->user_id,
            ], now()->addMinutes(15));
        }
        $request->session()->put('ine.recovery.token', $token);
        $request->session()->put('ine.recovery.searched', true);
        $request->session()->forget(['ine.recovery.otp_sent', 'ine.recovery.verified']);
        app(AuditLogger::class)->record('ine.recovery.searched', 'ine_recovery', null, [], []);

        return redirect()->route('ine.recover')->with('status', 'Si une correspondance existe, vous pourrez recevoir les instructions de vérification à l’adresse associée à votre compte.');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $token = (string) $request->session()->get('ine.recovery.token', '');
        $match = $token !== '' ? Cache::get('ine:recovery:match:'.$token) : null;
        if ($match) {
            $code = (string) random_int(100000, 999999);
            Cache::put('ine:recovery:otp:'.$token, [
                'user_id' => $match['user_id'],
                'profile_id' => $match['profile_id'],
                'hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ], now()->addMinutes(10));
            Cache::put('ine:recovery:attempts:'.$token, 0, now()->addMinutes(10));
            $recipient = User::query()->find($match['user_id']);
            if ($recipient) {
                SendIneRecoveryCode::dispatch($recipient->id, Crypt::encryptString($code), now()->addMinutes(10)->timestamp);
            }
            app(AuditLogger::class)->record('ine.recovery.otp_sent', 'student_profiles', (string) $match['profile_id'], [], ['channel' => 'email']);
        }
        $request->session()->put('ine.recovery.searched', true);
        $request->session()->put('ine.recovery.otp_sent', true);

        return redirect()->route('ine.recover')->with('status', 'Si une correspondance existe, un code temporaire a été envoyé à l’adresse associée au compte.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $token = (string) $request->session()->get('ine.recovery.token', '');
        $key = 'ine:recovery:otp:'.$token;
        $attemptKey = 'ine:recovery:attempts:'.$token;
        $challenge = $token !== '' ? Cache::get($key) : null;

        if (! $challenge || $challenge['expires_at'] < now()->timestamp) {
            Cache::forget($key);
            Cache::forget($attemptKey);
            throw ValidationException::withMessages(['code' => 'Le code est invalide ou expiré. Recommencez la recherche.']);
        }

        $attempts = Cache::increment($attemptKey);
        if (! Hash::check($data['code'], $challenge['hash'])) {
            if ($attempts >= 5) {
                Cache::forget($key);
                Cache::forget($attemptKey);
                Cache::forget('ine:recovery:match:'.$token);
            }
            app(AuditLogger::class)->record('ine.recovery.otp_failed', 'ine_recovery', null, [], []);
            throw ValidationException::withMessages(['code' => 'Le code est invalide ou expiré.']);
        }

        $profileIsVerified = DB::table('student_profiles')->where('id', $challenge['profile_id'])->where('user_id', $challenge['user_id'])->where('ine_status', 'verified')->exists();
        if (! $profileIsVerified) {
            Cache::forget($key);
            Cache::forget($attemptKey);
            Cache::forget('ine:recovery:match:'.$token);
            throw ValidationException::withMessages(['code' => 'Le code est invalide ou expiré.']);
        }

        Cache::forget($key);
        Cache::forget($attemptKey);
        Cache::forget('ine:recovery:match:'.$token);
        $request->session()->put('ine.recovery.verified', [
            'user_id' => $challenge['user_id'],
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);
        app(AuditLogger::class)->record('ine.recovery.verified', 'student_profiles', (string) $challenge['profile_id'], [], ['method' => 'email_otp']);

        return redirect()->route('ine.recover')->with('status', 'Identité vérifiée.');
    }

    private function identityMatches(object $record, array $data): bool
    {
        if (filled($record->first_name) && filled($record->last_name)) {
            return $this->normalize($record->first_name) === $this->normalize($data['first_name'])
                && $this->normalize($record->last_name) === $this->normalize($data['last_name'])
                && $this->normalize($record->birth_place) === $this->normalize($data['birth_place']);
        }

        $providedNames = [
            $this->normalize($data['first_name'].' '.$data['last_name']),
            $this->normalize($data['last_name'].' '.$data['first_name']),
        ];

        return in_array($this->normalize($record->name), $providedNames, true)
            && $this->normalize($record->birth_place) === $this->normalize($data['birth_place']);
    }

    private function assertNameMatches(string $accountName, string $firstName, string $lastName): void
    {
        $provided = [
            $this->normalize($firstName.' '.$lastName),
            $this->normalize($lastName.' '.$firstName),
        ];
        if (! in_array($this->normalize($accountName), $provided, true)) {
            throw ValidationException::withMessages(['identity' => 'Les informations fournies ne correspondent pas au compte connecté.']);
        }
    }

    private function normalize(?string $value): string
    {
        $normalized = Str::lower(Str::ascii(trim((string) $value)));
        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }
}