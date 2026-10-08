<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('father_first_name', 120)->nullable();
            $table->string('father_last_name', 120)->nullable();
            $table->string('father_residence_country', 100)->nullable();
            $table->string('father_function', 160)->nullable();
            $table->string('mother_first_name', 120)->nullable();
            $table->string('mother_last_name', 120)->nullable();
            $table->string('mother_residence_country', 100)->nullable();
            $table->string('mother_function', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'father_first_name', 'father_last_name', 'father_residence_country', 'father_function',
                'mother_first_name', 'mother_last_name', 'mother_residence_country', 'mother_function',
            ]);
        });
    }
};