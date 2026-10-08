<?php

namespace App\Filament\Pages;

use App\Services\DashboardStatisticsService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class StatisticsPage extends Page
{
    protected string $view = 'filament.pages.statistics';
    protected static ?string $title = 'Statistiques décisionnelles';
    protected static ?string $navigationLabel = 'Statistiques';
    protected static string|UnitEnum|null $navigationGroup = 'SYSTÈME';
    protected static string $permission = 'view_statistics';

    public static function canAccess(): bool
    {
        return Gate::allows(static::$permission);
    }

    public function getStatistics(): array
    {
        $filters = request()->only(['year', 'from', 'to', 'region', 'university_id', 'program_id', 'sex', 'status']);
        $service = app(DashboardStatisticsService::class);
        return ['filters' => $filters, 'summary' => $service->summary($filters), 'programs' => $service->programs($filters, 15), 'universities' => $service->universities($filters, 15), 'regions' => $service->regions($filters), 'trends' => $service->trends($filters)];
    }
}
