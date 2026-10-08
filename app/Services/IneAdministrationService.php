<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IneAdministrationService
{
    private const TRANSITIONS = [
        'pending' => ['verified', 'rejected'],
        'rejected' => ['verified'],
        'verified' => ['suspended'],
        'suspended' => ['verified'],
    ];

    public function changeStatus(User $actor, User $student, string $nextStatus): void
    {
        abort_unless($actor->can('users.update'), 403);
        abort_unless($student->account_type === 'etudiant', 404);

        DB::transaction(function () use ($actor, $student, $nextStatus): void {
            $profile = DB::table('student_profiles')->where('user_id', $student->id)->lockForUpdate()->first();
            abort_unless($profile && filled($profile->inee), 404);
            $currentStatus = $profile->ine_status ?? 'pending';
            if (! in_array($nextStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
                throw ValidationException::withMessages(['ine_status' => 'Cette transition de statut INE n’est pas autorisée.']);
            }

            $verified = $nextStatus === 'verified';
            DB::table('student_profiles')->where('id', $profile->id)->update([
                'ine_status' => $nextStatus,
                'ine_verified_at' => $verified ? now() : ($nextStatus === 'suspended' ? $profile->ine_verified_at : null),
                'ine_verified_by' => $verified ? $actor->id : ($nextStatus === 'suspended' ? $profile->ine_verified_by : null),
                'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('ine.status_changed', 'student_profiles', (string) $profile->id, ['ine_status' => $currentStatus], ['ine_status' => $nextStatus]);
        });
    }
}