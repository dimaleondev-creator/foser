<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->foreignUuid('university_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('program')->nullable()->after('address');
            $table->string('academic_year', 20)->nullable()->after('program');
            $table->string('validation_status', 30)->default('pending')->after('academic_year')->index();
        });

        Schema::create('university_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->string('filename');
            $table->string('status', 30)->default('completed')->index();
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('report')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('university_imports');
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('university_id');
            $table->dropColumn(['program', 'academic_year', 'validation_status']);
        });
    }
};