<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\DashboardStatisticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FinancialStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Suivi financier (XOF)';

    public static function canView(): bool
    {
        return Gate::any(['finance.view', 'view_financial_statistics']);
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardStatisticsService::class)->summary(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']));
        $committed = $metrics['committed'];
        $disbursed = $metrics['disbursed'];
        $paid = $metrics['paid'];
        $beneficiaries = DB::table('financial_commitments')->whereIn('status', ['valide', 'execute'])->whereNotNull('beneficiary_id')->distinct('beneficiary_id')->count('beneficiary_id');
        $payments = DB::table('payment_records');
        $todayPaid = (float) (clone $payments)->where('status', 'paid')->whereDate('paid_at', today())->sum('amount');
        $monthPaid = (float) (clone $payments)->where('status', 'paid')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $pending = (int) (clone $payments)->whereIn('status', ['pending', 'processing'])->count();
        $failed = (int) (clone $payments)->where('status', 'failed')->count();

        return [
            Stat::make('Montant engagé', number_format($committed, 0, ',', ' ').' XOF')->color('primary'),
            Stat::make('Montant décaissé', number_format($disbursed, 0, ',', ' ').' XOF')->color('info'),
            Stat::make('Montant payé', number_format($paid, 0, ',', ' ').' XOF')->color('success'),
            Stat::make('Montant restant', number_format(max(0, $committed - $paid), 0, ',', ' ').' XOF')->color('warning'),
            Stat::make('Bénéficiaires', number_format($beneficiaries, 0, ',', ' '))->color('gray'),
            Stat::make('Paiements en attente', number_format($pending, 0, ',', ' '))->color('warning'),
            Stat::make('Paiements échoués', number_format($failed, 0, ',', ' '))->color('danger'),
            Stat::make('Payé aujourd’hui', number_format($todayPaid, 0, ',', ' ').' XOF')->color('info'),
            Stat::make('Payé ce mois', number_format($monthPaid, 0, ',', ' ').' XOF')->color('success'),
        ];
    }
}
