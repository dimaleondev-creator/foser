<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_aids', function (Blueprint $table): void {
            $table->text('eligibility')->nullable();
            $table->text('beneficiaries')->nullable();
            $table->text('required_documents')->nullable();
            $table->text('conditions')->nullable();
            $table->text('procedure')->nullable();
            $table->string('processing_time', 120)->nullable();
        });

        Schema::table('study_loans', function (Blueprint $table): void {
            $table->text('eligibility')->nullable();
            $table->text('beneficiaries')->nullable();
            $table->text('study_levels')->nullable();
            $table->text('required_documents')->nullable();
            $table->text('procedure')->nullable();
        });

        Schema::table('research_programs', function (Blueprint $table): void {
            $table->text('beneficiaries')->nullable();
            $table->text('conditions')->nullable();
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->string('calendar', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
        });

        Schema::table('innovation_programs', function (Blueprint $table): void {
            $table->string('innovation_type', 40)->default('incubation');
            $table->text('eligibility')->nullable();
            $table->text('support')->nullable();
            $table->string('duration', 120)->nullable();
            $table->text('procedure')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
        });

        Schema::create('program_faqs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category', 40)->default('general')->index();
            $table->string('question');
            $table->text('answer');
            $table->string('status', 30)->default('published')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_faqs');

        Schema::table('innovation_programs', function (Blueprint $table): void {
            $table->dropColumn(['innovation_type', 'eligibility', 'support', 'duration', 'procedure', 'description', 'status']);
        });
        Schema::table('research_programs', function (Blueprint $table): void {
            $table->dropColumn(['beneficiaries', 'conditions', 'maximum_amount', 'calendar', 'description', 'status']);
        });
        Schema::table('study_loans', function (Blueprint $table): void {
            $table->dropColumn(['eligibility', 'beneficiaries', 'study_levels', 'required_documents', 'procedure']);
        });
        Schema::table('financial_aids', function (Blueprint $table): void {
            $table->dropColumn(['eligibility', 'beneficiaries', 'required_documents', 'conditions', 'procedure', 'processing_time']);
        });
    }
};