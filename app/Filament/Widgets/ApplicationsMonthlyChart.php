<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;
use App\Services\DashboardStatisticsService;

class ApplicationsMonthlyChart extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Dossiers par mois';

    protected string $color = 'info';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $labels = [];
        $values = [];

        for ($month = 5; $month >= 0; $month--) {
            $date = now()->subMonths($month);
            $labels[] = $date->translatedFormat('M Y');
            $values[] = 0;
        }

        try {
            $counts = collect(app(DashboardStatisticsService::class)->charts(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']), 100)['monthly'])->keyBy('label');

            foreach ($labels as $index => $label) {
                $period = now()->subMonths(5 - $index)->format('Y-m');
                $values[$index] = (int) ($counts[$period]['total'] ?? 0);
            }
        } catch (\Throwable) {
            // Keep the dashboard available while the database is unavailable.
        }

        return ['datasets' => [['label' => 'Dossiers', 'data' => $values]], 'labels' => $labels];
    }
}
