<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_loan_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('study_loan_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('interest_rate', 5, 2);
            $table->unsignedSmallInteger('grace_period_months')->default(0);
            $table->decimal('monthly_payment', 15, 2);
            $table->decimal('total_interest', 15, 2);
            $table->decimal('total_repayment', 15, 2);
            $table->date('first_due_at');
            $table->date('last_due_at');
            $table->json('simulation');
            $table->string('status', 30)->default('soumis')->index();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('study_loan_installments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_loan_application_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_at');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['study_loan_application_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_loan_installments');
        Schema::dropIfExists('study_loan_applications');
    }
};
