<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('evaluation_expertise')->nullable();
        });

        Schema::table('evaluations', function (Blueprint $table): void {
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn('validated_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('evaluation_expertise');
        });
    }
};