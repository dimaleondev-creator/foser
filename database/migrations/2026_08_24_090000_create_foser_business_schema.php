<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('researcher_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('researcher_number')->unique();
            $table->string('orcid', 19)->nullable()->unique();
            $table->string('speciality')->nullable();
            $table->string('academic_rank')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('universities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('short_name', 80)->nullable();
            $table->string('code', 80)->unique();
            $table->string('country', 100);
            $table->string('city', 120)->nullable();
            $table->string('website')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('university_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->default('staff');
            $table->timestamps();
            $table->unique(['university_id', 'user_id']);
        });

        Schema::create('laboratories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('university_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 80)->unique();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 80)->unique();
            $table->string('type', 40)->index();
            $table->text('description')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency', 4)->default('FCFA');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('financial_aids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('aid_type', 40);
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->string('frequency', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('study_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('repayment_months')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('research_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('research_area');
            $table->string('maturity_level', 40)->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'research_area']);
        });

        Schema::create('innovation_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('innovation_area');
            $table->string('target_stage', 40)->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'innovation_area']);
        });

        Schema::create('eligibility_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 40);
            $table->string('operator', 20)->default('equals');
            $table->jsonb('parameters');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['program_id', 'rule_type']);
        });

        Schema::create('required_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('label');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['program_id', 'document_type']);
        });

        Schema::create('call_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('call_categories')->nullOnDelete();
            $table->string('title');
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->date('opens_at');
            $table->date('closes_at');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['program_id', 'status']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('document_type', 80)->index();
            $table->string('disk', 40)->default('private');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 128)->nullable()->index();
            $table->string('visibility', 20)->default('private')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('document_categories')->nullOnDelete();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('downloaded_at')->useCurrent();
            $table->index(['document_id', 'downloaded_at']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('status', 40)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('applicant_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['call_id', 'applicant_id']);
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('required_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('submitted')->index();
            $table->timestamps();
            $table->unique(['application_id', 'document_id']);
        });

        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->index(['application_id', 'changed_at']);
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 3)->default(1);
            $table->decimal('maximum_score', 8, 2)->default(100);
            $table->timestamps();
            $table->unique(['program_id', 'name']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('assigned')->index();
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'evaluator_id']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('criterion_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->decimal('score', 8, 2);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'criterion_id']);
        });

        Schema::create('application_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 30)->index();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('research_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('laboratory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('principal_researcher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('reference')->unique();
            $table->text('abstract')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('research_project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 40);
            $table->timestamps();
            $table->unique(['research_project_id', 'user_id']);
        });

        Schema::create('research_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('doi', 120)->nullable()->unique();
            $table->string('publication_type', 40);
            $table->date('published_on')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('research_project_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained()->restrictOnDelete();
            $table->string('document_role', 40)->nullable();
            $table->timestamps();
            $table->unique(['research_project_id', 'document_id']);
        });

        Schema::create('research_project_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('status', 30)->default('assigned')->index();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['research_project_id', 'evaluator_id']);
        });

        Schema::create('financial_commitments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('research_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 4)->default('FCFA');
            $table->string('status', 30)->default('approved')->index();
            $table->date('committed_at');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commitment_id')->constrained('financial_commitments')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('planned')->index();
            $table->date('scheduled_for')->nullable();
            $table->date('disbursed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('disbursement_id')->constrained()->cascadeOnDelete();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('payment_method', 40)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('research_project_disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('disbursement_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['research_project_id', 'disbursement_id']);
        });

        Schema::create('news_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('news', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->nullable()->constrained('news_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('press_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 40)->default('all')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_albums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('album_id')->nullable()->constrained('media_albums')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('media_type', 30)->index();
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('external_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('thread_id');
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('message_thread_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['thread_id', 'user_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('thread_id')->references('id')->on('message_threads')->cascadeOnDelete();
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('claimant_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->string('auditable_type', 150);
            $table->uuid('auditable_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'system_settings', 'audit_logs', 'claims', 'message_thread_users', 'messages',
            'message_threads', 'videos', 'media', 'media_albums', 'announcements',
            'press_releases', 'events', 'news', 'news_categories', 'payment_records',
            'research_project_disbursements', 'disbursements', 'financial_commitments', 'research_project_evaluations',
            'research_project_documents', 'research_publications', 'research_project_members',
            'research_projects', 'application_results', 'evaluation_scores', 'evaluations',
            'evaluation_criteria', 'application_status_histories', 'application_documents',
            'applications', 'downloads', 'document_versions', 'documents', 'document_categories',
            'calls', 'call_categories', 'required_documents', 'eligibility_rules',
            'innovation_programs', 'research_programs', 'study_loans', 'financial_aids',
            'programs', 'laboratories', 'university_users', 'universities', 'researcher_profiles',
            'student_profiles',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
