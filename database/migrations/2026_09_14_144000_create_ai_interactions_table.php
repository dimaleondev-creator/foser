<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_interactions')) {
            return;
        }

        Schema::create('ai_interactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 120)->nullable()->index();
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('feature', 40)->default('assistant')->index();
            $table->text('question');
            $table->longText('response')->nullable();
            $table->json('sources')->nullable();
            $table->string('risk_level', 20)->default('normal')->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('status', 20)->default('completed')->index();
            $table->smallInteger('feedback')->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // This table may have predated the migration ledger; preserve existing data.
    }
};
