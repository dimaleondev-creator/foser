<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CallCapacityService
{
    public function lockAndAssertAvailable(string $callId, string $applicationId): void
    {
        $call = DB::table('calls')->where('id', $callId)->lockForUpdate()->firstOrFail();
        $today = today();
        abort_unless(in_array($call->status, ['published', 'open', 'scheduled'], true)
            && Carbon::parse($call->opens_at)->startOfDay()->lte($today)
            && Carbon::parse($call->closes_at)->startOfDay()->gte($today)
            && (! isset($call->published_at) || Carbon::parse($call->published_at)->lte(now())), 422, 'L’appel est fermé.');

        if (! $call->places) {
            return;
        }

        $activeApplications = DB::table('applications')
            ->where('call_id', $callId)
            ->where('id', '!=', $applicationId)
            ->whereNotIn('status', ['brouillon', 'draft', 'rejete', 'rejected', 'cloture', 'cancelled', 'archive', 'archived'])
            ->count();

        if ($activeApplications >= $call->places) {
            throw ValidationException::withMessages(['places' => 'Le nombre de places disponibles est atteint.']);
        }
    }
}