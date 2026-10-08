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
            $metrics = app(DashboardStatisticsService::class)->summary(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']));
        } catch (\Throwable) {
            $metrics = ['students' => 0, 'researchers' => 0, 'universities' => 0, 'active_programs' => 0, 'beneficiaries' => 0, 'new_students' => 0, 'open_calls' => 0, 'applications' => 0, 'validated' => 0, 'rejected' => 0, 'pending' => 0, 'committed' => 0, 'disbursed' => 0, 'paid' => 0, 'funded_projects' => 0, 'treatment_rate' => 0, 'remaining' => 0];
        }
        return [
            Stat::make('Etudiants inscrits', number_format($metrics['students'], 0, ',', ' '))
                ->description('Profils enregistrés')
                ->color('primary'),
            Stat::make('Chercheurs', number_format($metrics['researchers'], 0, ',', ' '))->color('info'),
            Stat::make('Universités actives', number_format($metrics['universities'], 0, ',', ' '))->color('primary'),
            Stat::make('Programmes actifs', number_format($metrics['active_programs'], 0, ',', ' '))->color('success'),
            Stat::make('Bénéficiaires', number_format($metrics['beneficiaries'], 0, ',', ' '))->color('success'),
            Stat::make('Nouveaux étudiants', number_format($metrics['new_students'], 0, ',', ' '))
                ->description('Derniers 30 jours')
                ->color('info'),
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
            Stat::make('Projets financés', number_format($metrics['funded_projects'], 0, ',', ' '))
                ->description('Projets de recherche')
                ->color('primary'),
            Stat::make('Appels ouverts', number_format($metrics['open_calls'], 0, ',', ' '))
                ->description('Appels actuellement actifs')
                ->color('warning'),
            Stat::make('Taux de traitement', $metrics['treatment_rate'].' %')
                ->description('Dossiers traités')
                ->color('info'),
        ];
    }

}
