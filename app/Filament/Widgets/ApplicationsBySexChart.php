<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;

class ApplicationsBySexChart extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Dossiers par sexe';
    protected string $color = 'success';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = app(DashboardStatisticsService::class)->charts(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']), 10)['sex'];
        return ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Dossiers', 'data' => array_column($rows, 'total')]]];
    }
}
