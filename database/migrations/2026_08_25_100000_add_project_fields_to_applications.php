<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('project_title')->nullable()->after('applicant_note');
            $table->text('summary')->nullable()->after('project_title');
            $table->longText('description')->nullable()->after('summary');
            $table->string('domain')->nullable()->after('description');
            $table->text('objectives')->nullable()->after('domain');
            $table->longText('methodology')->nullable()->after('objectives');
            $table->text('calendar')->nullable()->after('methodology');
            $table->decimal('budget', 15, 2)->nullable()->after('calendar');
            $table->text('team')->nullable()->after('budget');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['project_title', 'summary', 'description', 'domain', 'objectives', 'methodology', 'calendar', 'budget', 'team']);
        });
    }
};