<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\DashboardStatisticsService;

class FoserStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Indicateurs FOSER';

    protected function getStats(): array
    {
        try {
            $metrics = app(DashboardStatisticsService::class)->summary(request()->only(['year', 'region', 'university_id', 'program_id', 'sex', 'status']));
        } catch (\Throwable) {
            $metrics = ['students' => 0, 'applications' => 0, 'validated' => 0, 'rejected' => 0, 'pending' => 0, 'committed' => 0, 'disbursed' => 0, 'paid' => 0, 'funded_projects' => 0, 'treatment_rate' => 0];
        }
        return [
            Stat::make('Etudiants inscrits', number_format($metrics['students'], 0, ',', ' '))
                ->description('Profils enregistrés')
                ->color('primary'),
            Stat::make('Dossiers déposés', number_format($metrics['applications'], 0, ',', ' '))
                ->description('Toutes candidatures')
                ->color('info'),
            Stat::make('Dossiers en attente', number_format($metrics['pending'], 0, ',', ' '))
                ->description('En cours de traitement')
                ->color('warning'),
            Stat::make('Dossiers validés', number_format($metrics['validated'], 0, ',', ' '))
                ->description('Décisions favorables')
                ->color('success'),
            Stat::make('Dossiers rejetés', number_format($metrics['rejected'], 0, ',', ' '))
                ->description('Décisions défavorables')
                ->color('danger'),
            Stat::make('Montants engagés', number_format($metrics['committed'], 0, ',', ' '))
                ->description('En GNF')
                ->color('gray'),
            Stat::make('Montants décaissés', number_format($metrics['disbursed'], 0, ',', ' '))
                ->description('En GNF')
                ->color('success'),
            Stat::make('Montant payé', number_format($metrics['paid'], 0, ',', ' '))
                ->description('Paiements exécutés')
                ->color('success'),
            Stat::make('Projets financés', number_format($metrics['funded_projects'], 0, ',', ' '))
                ->description('Projets de recherche')
                ->color('primary'),
            Stat::make('Taux de traitement', $metrics['treatment_rate'].' %')
                ->description('Dossiers traités')
                ->color('info'),
        ];
    }

}
