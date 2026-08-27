<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('workflow_status', 40)->default('draft')->index()->after('status');
        });

        $mapping = [
            'brouillon' => 'draft', 'soumis' => 'submitted', 'verification' => 'completeness_check',
            'incomplet' => 'documents_pending', 'complement' => 'documents_pending', 'recevable' => 'university_review',
            'eligible' => 'university_review', 'evaluation' => 'evaluation', 'decision' => 'commission_review',
            'valide' => 'commission_review', 'approuve' => 'awarded', 'engage' => 'committed',
            'decaisse' => 'disbursement_pending', 'paye' => 'disbursed', 'rejete' => 'rejected', 'cloture' => 'cancelled',
        ];
        foreach ($mapping as $legacy => $canonical) {
            DB::table('applications')->where('status', $legacy)->update(['workflow_status' => $canonical]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('workflow_status'));
    }
};
