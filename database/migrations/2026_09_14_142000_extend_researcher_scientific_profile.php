<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->text('keywords')->nullable();
            $table->string('google_scholar_url')->nullable();
            $table->string('researchgate_url')->nullable();
            $table->string('department', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table): void {
            $table->dropColumn(['date_of_birth', 'nationality', 'keywords', 'google_scholar_url', 'researchgate_url', 'department']);
        });
    }
};
