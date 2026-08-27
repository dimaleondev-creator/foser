<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->timestamp('due_at')->nullable()->after('assigned_at')->index();
            $table->string('priority', 20)->default('normal')->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropColumn(['due_at', 'priority']);
        });
    }
};
