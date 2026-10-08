<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->string('image_path')->nullable();
            $table->foreignUuid('media_album_id')->nullable()->constrained('media_albums')->nullOnDelete();
            $table->string('visibility', 20)->default('public')->index();
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->text('external_url')->nullable();
            $table->string('path')->nullable()->change();
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->timestamp('unsubscribe_expires_at')->nullable();
        });

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::create('contact_message_replies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('delivery_status', 30)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('subject');
            $table->longText('body');
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('contact_message_replies');
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropColumn(['read_at', 'closed_at']);
        });
        Schema::table('news', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_album_id');
            $table->dropColumn(['image_path', 'visibility']);
        });
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('external_url');
        });
        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->dropColumn('unsubscribe_expires_at');
        });
    }
};