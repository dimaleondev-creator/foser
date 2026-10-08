<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;

class ApplicationsAnnualChart extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Évolution annuelle des dossiers';
    protected string $color = 'primary';
    protected function getType(): string { return 'line'; }
    protected function getData(): array
    {
        $rows = app(DashboardStatisticsService::class)->charts($this->filters(), 100)['annual'];
        return ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Dossiers déposés', 'data' => array_column($rows, 'total')]]];
    }
    private function filters(): array { return request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']); }
}
