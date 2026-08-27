<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event', 80);
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['event', 'channel']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'event', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80)->index();
            $table->string('channel', 20);
            $table->string('idempotency_key', 191);
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['idempotency_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};
