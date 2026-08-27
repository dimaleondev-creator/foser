<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_awards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('award_date');
            $table->string('decision_reference')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_awards');
    }
};