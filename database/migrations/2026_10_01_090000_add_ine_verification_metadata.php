<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('first_name', 120)->nullable()->after('user_id');
            $table->string('last_name', 120)->nullable()->after('first_name');
            $table->string('ine_status', 20)->default('pending')->index()->after('inee');
            $table->timestamp('ine_verified_at')->nullable()->after('ine_status');
            $table->foreignId('ine_verified_by')->nullable()->after('ine_verified_at')->constrained('users')->nullOnDelete();
            $table->string('inee', 255)->nullable()->change();
        });

        if (! Schema::hasIndex('student_profiles', ['inee'], 'unique')) {
            Schema::table('student_profiles', fn (Blueprint $table) => $table->unique('inee'));
        }
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ine_verified_by');
            $table->dropIndex(['ine_status']);
            $table->dropColumn(['first_name', 'last_name', 'ine_status', 'ine_verified_at']);
        });
    }
};