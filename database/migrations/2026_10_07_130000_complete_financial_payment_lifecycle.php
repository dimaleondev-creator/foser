<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_commitments', function (Blueprint $table): void {
            $table->foreignUuid('program_id')->nullable()->after('research_project_id')->constrained()->nullOnDelete();
            $table->decimal('budget', 15, 2)->nullable()->after('amount');
            $table->unsignedSmallInteger('fiscal_year')->nullable()->after('currency');
        });

        Schema::table('disbursements', function (Blueprint $table): void {
            $table->unsignedSmallInteger('installment_number')->nullable()->after('amount');
            $table->string('supporting_document_path')->nullable()->after('disbursed_at');
        });

        Schema::table('payment_records', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->nullable()->unique()->after('provider_reference');
            $table->foreignId('initiated_by')->nullable()->after('processed_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->nullable()->constrained('payment_records')->nullOnDelete();
            $table->string('reference')->unique();
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type', 40);
            $table->string('status', 30)->index();
            $table->timestamp('transaction_at');
            $table->json('metadata')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_reference')->nullable()->index();
            $table->string('idempotency_key', 160)->nullable()->unique();
            $table->timestamps();
            $table->index(['beneficiary_id', 'transaction_at']);
        });

        Schema::create('financial_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payment_records')->restrictOnDelete();
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2);
            $table->decimal('received_amount', 15, 2);
            $table->string('external_reference')->nullable()->index();
            $table->string('status', 30)->index();
            $table->text('notes')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at');
            $table->timestamps();
            $table->index(['payment_id', 'reconciled_at']);
        });

        Schema::create('financial_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->unique()->constrained('payment_records')->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_receipts');
        Schema::dropIfExists('financial_reconciliations');
        Schema::dropIfExists('financial_transactions');

        Schema::table('payment_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
        Schema::table('disbursements', function (Blueprint $table): void {
            $table->dropColumn(['installment_number', 'supporting_document_path']);
        });
        Schema::table('financial_commitments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn(['budget', 'fiscal_year']);
        });
    }
};