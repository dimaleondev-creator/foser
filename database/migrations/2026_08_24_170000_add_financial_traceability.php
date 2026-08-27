<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['financial_commitments', 'disbursements', 'payment_records'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($tableName !== 'disbursements') $table->foreignId('beneficiary_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->text('comment')->nullable()->after('processed_by');
                $table->timestamp('executed_at')->nullable()->after('comment');
                $table->timestamp('cancelled_at')->nullable()->after('executed_at');
            });
        }

        Schema::create('financial_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation_type', 50);
            $table->string('operation_id', 36);
            $table->string('event', 100);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['operation_type', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
        foreach (['financial_commitments', 'disbursements', 'payment_records'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($tableName !== 'disbursements') $table->dropConstrainedForeignId('beneficiary_id');
                $table->dropConstrainedForeignId('processed_by');
                $table->dropColumn(['comment', 'executed_at', 'cancelled_at']);
            });
        }
    }
};
