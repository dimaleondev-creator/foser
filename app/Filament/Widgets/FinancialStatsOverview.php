<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\DashboardStatisticsService;
use Illuminate\Support\Facades\DB;

class FinancialStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Suivi financier (XOF)';

    protected function getStats(): array
    {
        $metrics = app(DashboardStatisticsService::class)->summary(request()->only(['year', 'region', 'university_id', 'program_id', 'sex', 'status']));
        $committed = $metrics['committed'];
        $disbursed = $metrics['disbursed'];
        $paid = $metrics['paid'];
        $beneficiaries = DB::table('financial_commitments')->whereIn('status', ['valide', 'execute'])->whereNotNull('beneficiary_id')->distinct('beneficiary_id')->count('beneficiary_id');

        return [
            Stat::make('Montant engagé', number_format($committed, 0, ',', ' ').' XOF')->color('primary'),
            Stat::make('Montant décaissé', number_format($disbursed, 0, ',', ' ').' XOF')->color('info'),
            Stat::make('Montant payé', number_format($paid, 0, ',', ' ').' XOF')->color('success'),
            Stat::make('Montant restant', number_format(max(0, $committed - $paid), 0, ',', ' ').' XOF')->color('warning'),
            Stat::make('Bénéficiaires', number_format($beneficiaries, 0, ',', ' '))->color('gray'),
        ];
    }
}
