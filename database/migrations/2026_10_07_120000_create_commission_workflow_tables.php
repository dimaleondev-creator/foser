<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('call_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type', 60);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('venue', 255)->nullable();
            $table->text('agenda')->nullable();
            $table->text('convocation_text')->nullable();
            $table->timestamp('convocation_sent_at')->nullable();
            $table->unsignedTinyInteger('quorum_percentage')->default(50);
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['call_id', 'status']);
            $table->index(['program_id', 'scheduled_at']);
        });

        Schema::create('commission_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 30)->default('member');
            $table->string('attendance_status', 20)->default('pending')->index();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
            $table->unique(['commission_id', 'user_id']);
            $table->index(['commission_id', 'role']);
        });

        Schema::create('commission_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->unique(['commission_id', 'application_id']);
            $table->index('application_id');
        });

        Schema::create('commission_votes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commission_application_id')->constrained('commission_applications')->cascadeOnDelete();
            $table->foreignUuid('commission_member_id')->constrained('commission_members')->restrictOnDelete();
            $table->string('vote', 20);
            $table->text('comment')->nullable();
            $table->timestamp('voted_at');
            $table->timestamps();
            $table->unique(['commission_application_id', 'commission_member_id']);
            $table->index(['commission_id', 'vote']);
        });

        Schema::create('commission_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commission_application_id')->constrained('commission_applications')->cascadeOnDelete();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 40);
            $table->string('status', 30)->default('pending_validation')->index();
            $table->text('justification');
            $table->timestamp('decided_at');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->unique('commission_application_id');
        });

        Schema::create('commission_minutes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary');
            $table->longText('content');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_minutes');
        Schema::dropIfExists('commission_decisions');
        Schema::dropIfExists('commission_votes');
        Schema::dropIfExists('commission_applications');
        Schema::dropIfExists('commission_members');
        Schema::dropIfExists('commissions');
    }
};