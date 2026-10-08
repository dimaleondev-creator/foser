<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;

class ApplicationsByRegionChart extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Dossiers par région';
    protected string $color = 'warning';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = app(DashboardStatisticsService::class)->charts(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']), 10)['regions'];
        return ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Dossiers', 'data' => array_column($rows, 'total')]]];
    }
}
