<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_programs', function (Blueprint $table): void {
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->string('duration', 120)->nullable();
            $table->text('establishments')->nullable();
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->string('contact', 255)->nullable();
            $table->string('document_path')->nullable();
        });

        Schema::table('calls', function (Blueprint $table): void {
            $table->text('objectives')->nullable();
            $table->text('domains')->nullable();
            $table->text('beneficiaries')->nullable();
            $table->decimal('available_budget', 15, 2)->nullable();
            $table->decimal('maximum_project_amount', 15, 2)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('contact', 255)->nullable();
            $table->string('application_url')->nullable();
            $table->string('regulation_path')->nullable();
            $table->string('terms_path')->nullable();
        });

        Schema::table('research_projects', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->text('expected_results')->nullable();
            $table->text('achieved_results')->nullable();
            $table->string('domain')->nullable()->index();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->decimal('funded_amount', 15, 2)->nullable();
            $table->string('contact', 255)->nullable();
        });

        Schema::table('innovation_programs', function (Blueprint $table): void {
            $table->text('objectives')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('phases')->nullable();
            $table->text('training')->nullable();
            $table->text('mentoring')->nullable();
            $table->text('technical_support')->nullable();
            $table->text('entrepreneurial_support')->nullable();
            $table->text('partners')->nullable();
            $table->text('resources')->nullable();
            $table->string('calendar', 255)->nullable();
            $table->string('contact', 255)->nullable();
            $table->text('prizes')->nullable();
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->date('proclamation_at')->nullable();
            $table->text('technology')->nullable();
            $table->text('results')->nullable();
            $table->text('patents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('innovation_programs', function (Blueprint $table): void {
            $table->dropColumn(['objectives', 'target_audience', 'phases', 'training', 'mentoring', 'technical_support', 'entrepreneurial_support', 'partners', 'resources', 'calendar', 'contact', 'prizes', 'opens_at', 'closes_at', 'proclamation_at', 'technology', 'results', 'patents']);
        });
        Schema::table('research_projects', function (Blueprint $table): void {
            $table->dropColumn(['description', 'expected_results', 'achieved_results', 'domain', 'year', 'funded_amount', 'contact']);
        });
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropColumn(['objectives', 'domains', 'beneficiaries', 'available_budget', 'maximum_project_amount', 'published_at', 'contact', 'application_url', 'regulation_path', 'terms_path']);
        });
        Schema::table('research_programs', function (Blueprint $table): void {
            $table->dropColumn(['minimum_amount', 'duration', 'establishments', 'opens_at', 'closes_at', 'contact', 'document_path']);
        });
    }
};