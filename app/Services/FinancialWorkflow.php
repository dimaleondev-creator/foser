<?php

namespace App\Services;

use App\Enums\FinancialOperationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialWorkflow
{
    private const TRANSITIONS = [
        'brouillon' => ['soumis', 'annule'],
        'soumis' => ['valide', 'rejete', 'annule'],
        'valide' => ['execute', 'annule'],
        'rejete' => ['brouillon'],
        'execute' => ['annule'],
        'annule' => [],
    ];

    public function transition(string $table, string $id, FinancialOperationStatus $next, User $actor, ?string $comment = null): void
    {
        $permission = match ($next) {
            FinancialOperationStatus::VALIDE, FinancialOperationStatus::REJETE => 'finance.validate',
            FinancialOperationStatus::EXECUTE => 'finance.execute',
            FinancialOperationStatus::ANNULE => 'finance.authorize',
            default => 'finance.manage',
        };
        abort_unless($actor->can($permission) || $actor->can('finance.manage'), 403);
        $operation = DB::table($table)->where('id', $id)->firstOrFail();
        if (! in_array($next->value, self::TRANSITIONS[$operation->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Cette transition financière n’est pas autorisée.']);
        }
        DB::transaction(function () use ($table, $id, $next, $actor, $comment, $operation): void {
            $values = ['status' => $next->value, 'processed_by' => $actor->id, 'comment' => $comment, 'updated_at' => now()];
            if ($next === FinancialOperationStatus::EXECUTE) $values['executed_at'] = now();
            if ($next === FinancialOperationStatus::ANNULE) $values['cancelled_at'] = now();
            DB::table($table)->where('id', $id)->update($values);
            DB::table('financial_audit_logs')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'operation_type' => $table, 'operation_id' => $id, 'event' => 'financial.status_changed', 'old_values' => json_encode(['status' => $operation->status]), 'new_values' => json_encode(['status' => $next->value, 'comment' => $comment]), 'created_at' => now()]);
        });
        app(DashboardStatisticsService::class)->invalidate();
    }

    public function cancel(string $table, string $id, User $actor, string $reason): void
    {
        abort_unless(filled($reason), 422);
        $this->transition($table, $id, FinancialOperationStatus::ANNULE, $actor, $reason);
    }
}
