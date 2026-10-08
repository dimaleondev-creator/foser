<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('reference', 120)->nullable()->after('author')->index();
            $table->date('document_date')->nullable()->after('reference')->index();
            $table->string('version_label', 50)->nullable()->after('document_date');
        });

        Schema::table('document_versions', function (Blueprint $table): void {
            $table->string('disk', 40)->default('private')->after('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['reference', 'document_date', 'version_label']);
        });

        Schema::table('document_versions', function (Blueprint $table): void {
            $table->dropColumn('disk');
        });
    }
};