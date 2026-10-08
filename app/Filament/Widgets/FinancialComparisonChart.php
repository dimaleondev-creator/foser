<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Gate;

class FinancialComparisonChart extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Engagements, décaissements et paiements';
    protected string $color = 'success';
    public static function canView(): bool { return Gate::any(['finance.view', 'view_financial_statistics']); }
    protected function getType(): string { return 'bar'; }
    protected function getData(): array
    {
        $rows = app(DashboardStatisticsService::class)->charts(request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']), 3)['finance'];
        return ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Montant', 'data' => array_column($rows, 'total')]]];
    }
}
