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
            'old_values' => $old ? json_encode($this->redact($old)) : null, 'new_values' => $new ? json_encode($this->redact($new)) : null,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]);
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token|inee|email|phone|address|birth|national_id|nip|father|mother|ip_address/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}