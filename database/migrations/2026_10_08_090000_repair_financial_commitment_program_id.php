<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_commitments') || Schema::hasColumn('financial_commitments', 'program_id')) {
            return;
        }

        Schema::table('financial_commitments', function (Blueprint $table): void {
            $table->foreignUuid('program_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Keep the repaired column; the original lifecycle migration owns it.
    }
};