<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_units', function (Blueprint $table): void {
            $table->string('unit_type', 40)->default('direction')->after('responsible_id')->index();
            $table->string('acronym', 30)->nullable()->after('name_fr');
            $table->text('description_fr')->nullable()->after('function_en');
            $table->string('professional_email', 255)->nullable()->after('description_fr');
            $table->string('professional_phone', 40)->nullable()->after('professional_email');
        });

        Schema::create('institution_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description');
            $table->string('icon', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_values');
        Schema::table('organization_units', function (Blueprint $table): void {
            $table->dropColumn(['unit_type', 'acronym', 'description_fr', 'professional_email', 'professional_phone']);
        });
    }
};