<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('birth_place', 120)->nullable();
            $table->string('national_id', 120)->nullable();
            $table->string('faculty', 160)->nullable();
            $table->string('study_level', 80)->nullable();
            $table->string('emergency_contact_name', 160)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropColumn(['birth_place', 'national_id', 'faculty', 'study_level', 'emergency_contact_name', 'emergency_contact_phone', 'avatar_path']);
        });
    }
};
