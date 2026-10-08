<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->unsignedInteger('places')->nullable()->after('description');
            $table->decimal('amount', 15, 2)->nullable()->after('places');
            $table->string('currency', 4)->default('FCFA')->after('amount');
            $table->text('conditions')->nullable()->after('currency');
            $table->text('required_documents')->nullable()->after('conditions');
            $table->json('eligibility_roles')->nullable()->after('conditions');
            $table->timestamp('results_published_at')->nullable()->after('status');
        });

        Schema::create('call_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->string('label');
            $table->timestamps();
            $table->unique(['call_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_documents');
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropColumn(['places', 'amount', 'currency', 'conditions', 'required_documents', 'eligibility_roles', 'results_published_at']);
        });
    }
};