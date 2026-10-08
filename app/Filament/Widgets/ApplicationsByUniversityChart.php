<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;

class ApplicationsByUniversityChart extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Dossiers par université';
    protected string $color = 'primary';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = app(DashboardStatisticsService::class)->charts(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']), 10)['universities'];
        return ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Dossiers', 'data' => array_column($rows, 'total')]]];
    }
}
