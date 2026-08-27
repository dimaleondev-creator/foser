<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
            $table->unsignedSmallInteger('year')->nullable()->index()->after('category_id');
            $table->string('author')->nullable()->after('year');
            $table->string('language', 10)->default('fr')->index()->after('author');
            $table->string('status', 30)->default('draft')->index()->after('visibility');
            $table->timestamp('published_at')->nullable()->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['description', 'year', 'author', 'language', 'status', 'published_at']);
        });
    }
};
