<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class FinancialProgramBreakdown extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Paiements confirmés par programme';

    public static function canView(): bool
    {
        return Gate::any(['finance.view', 'view_financial_statistics']);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $hasCommitmentProgram = Schema::hasColumn('financial_commitments', 'program_id');
        $labelExpression = $hasCommitmentProgram
            ? "COALESCE(commitment_programs.name, application_programs.name, 'Non attribué')"
            : "COALESCE(application_programs.name, 'Non attribué')";

        $query = DB::table('payment_records')
            ->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')
            ->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->leftJoin('applications', 'applications.id', '=', 'financial_commitments.application_id');

        if ($hasCommitmentProgram) {
            $query->leftJoin('programs as commitment_programs', 'commitment_programs.id', '=', 'financial_commitments.program_id');
        }

        $rows = $query
            ->leftJoin('programs as application_programs', 'application_programs.id', '=', 'applications.program_id')
            ->where('payment_records.status', 'paid')
            ->selectRaw($labelExpression.' as label, SUM(payment_records.amount) as total')
            ->groupByRaw($labelExpression)
            ->orderByDesc('total')
            ->get();

        return ['labels' => $rows->pluck('label')->all(), 'datasets' => [['label' => 'XOF payé', 'data' => $rows->pluck('total')->map(fn ($amount): float => (float) $amount)->all()]]];
    }
}