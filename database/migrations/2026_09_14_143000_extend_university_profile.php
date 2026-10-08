<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('universities', function (Blueprint $table): void {
            $table->string('institution_type', 30)->default('public')->index();
            $table->date('founded_at')->nullable();
            $table->string('accreditation_number', 120)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('commune', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('responsible_name', 160)->nullable();
            $table->string('responsible_function', 120)->nullable();
            $table->string('responsible_phone', 40)->nullable();
            $table->string('responsible_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('universities', function (Blueprint $table): void {
            $table->dropColumn(['institution_type', 'founded_at', 'accreditation_number', 'region', 'province', 'commune', 'address', 'phone', 'email', 'logo_path', 'responsible_name', 'responsible_function', 'responsible_phone', 'responsible_email']);
        });
    }
};
