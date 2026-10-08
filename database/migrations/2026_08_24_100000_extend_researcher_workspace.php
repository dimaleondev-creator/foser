<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table): void {
            $table->foreignUuid('university_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignUuid('laboratory_id')->nullable()->after('university_id')->constrained()->nullOnDelete();
            $table->string('research_domain')->nullable()->after('speciality');
            $table->foreignUuid('cv_document_id')->nullable()->after('phone')->constrained('documents')->nullOnDelete();
        });

        Schema::table('research_projects', function (Blueprint $table): void {
            $table->decimal('budget', 15, 2)->nullable()->after('abstract');
            $table->string('currency', 4)->default('FCFA')->after('budget');
        });

        Schema::table('research_publications', function (Blueprint $table): void {
            $table->foreignId('author_id')->nullable()->after('research_project_id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('document_id')->nullable()->after('published_on')->constrained('documents')->nullOnDelete();
        });

        Schema::create('research_conventions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('title');
            $table->string('status', 30)->default('draft')->index();
            $table->date('signed_at')->nullable();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_conventions');
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('author_id');
            $table->dropConstrainedForeignId('document_id');
        });
        Schema::table('research_projects', function (Blueprint $table): void {
            $table->dropColumn(['budget', 'currency']);
        });
        Schema::table('researcher_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('university_id');
            $table->dropConstrainedForeignId('laboratory_id');
            $table->dropColumn(['research_domain', 'cv_document_id']);
        });
    }
};