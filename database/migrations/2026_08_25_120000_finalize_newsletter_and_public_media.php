<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->string('confirmation_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirmation_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('unsubscribe_token_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->dropColumn(['confirmation_token_hash', 'confirmation_expires_at', 'confirmed_at', 'unsubscribe_token_hash']);
        });
    }
};