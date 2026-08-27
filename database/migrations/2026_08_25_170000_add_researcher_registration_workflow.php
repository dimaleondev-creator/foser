<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table): void {
            $table->string('status', 20)->default('approved')->index();
            $table->string('registration_reference', 40)->nullable()->unique();
            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('position', 150)->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->longText('biography')->nullable();
            $table->longText('main_publications')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['status', 'registration_reference', 'country', 'region', 'city', 'position', 'years_experience', 'biography', 'main_publications', 'rejection_reason', 'approved_at', 'rejected_at', 'suspended_at']);
        });
    }
};