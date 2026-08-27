<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('category', 80)->nullable()->after('organizer_id')->index();
            $table->string('image_path')->nullable()->after('ends_at');
            $table->string('external_url')->nullable()->after('image_path');
            $table->boolean('is_featured')->default(false)->after('status')->index();
            $table->unsignedInteger('capacity')->nullable()->after('is_featured');
            $table->string('registration_url')->nullable()->after('capacity');
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['status', 'starts_at']);
            $table->dropColumn(['category', 'image_path', 'external_url', 'is_featured', 'capacity', 'registration_url']);
        });
    }
};