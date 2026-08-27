<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogger
{
    public function record(string $event, string $type, ?string $id, array $old = [], array $new = []): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(), 'user_id' => Auth::id(), 'event' => $event,
            'auditable_type' => $type, 'auditable_id' => $id,
            'old_values' => $old ? json_encode($old) : null, 'new_values' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]);
    }
}