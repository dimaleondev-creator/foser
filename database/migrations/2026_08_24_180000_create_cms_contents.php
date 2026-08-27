<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_contents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('content_key', 100);
            $table->string('locale', 5)->default('fr');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['content_key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_contents');
    }
};
