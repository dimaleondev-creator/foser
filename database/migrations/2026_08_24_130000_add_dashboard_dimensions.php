<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('region', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('region');
            $table->string('sex', 20)->nullable()->after('province');
            $table->index(['university_id', 'region', 'province', 'sex']);
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->index(['created_at', 'status']);
            $table->index(['program_id', 'status']);
        });

        Schema::table('financial_commitments', function (Blueprint $table): void {
            $table->index(['application_id', 'status']);
            $table->index(['research_project_id', 'status']);
        });

        Schema::table('disbursements', function (Blueprint $table): void {
            $table->index(['commitment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('disbursements', fn (Blueprint $table) => $table->dropIndex(['commitment_id', 'status']));
        Schema::table('financial_commitments', function (Blueprint $table): void {
            $table->dropIndex(['application_id', 'status']);
            $table->dropIndex(['research_project_id', 'status']);
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['created_at', 'status']);
            $table->dropIndex(['program_id', 'status']);
        });
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropIndex(['university_id', 'region', 'province', 'sex']);
            $table->dropColumn(['region', 'province', 'sex']);
        });
    }
};