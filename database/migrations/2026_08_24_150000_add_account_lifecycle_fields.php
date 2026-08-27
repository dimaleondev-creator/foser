<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 40)->default('etudiant')->after('password')->index();
            $table->string('status', 20)->default('active')->after('account_type')->index();
            $table->timestamp('suspended_at')->nullable()->after('status');
        });

        Schema::create('account_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['account_type', 'status', 'suspended_at']);
        });
    }
};
