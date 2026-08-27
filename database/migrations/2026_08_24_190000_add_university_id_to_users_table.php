<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('university_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->index(['account_type', 'status', 'university_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['university_id']);
            $table->dropIndex(['account_type', 'status', 'university_id']);
            $table->dropColumn('university_id');
        });
    }
};