<?php

namespace App\Filament\Widgets;

use App\Models\Commission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CommissionOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('À planifier', Commission::query()->where('status', 'draft')->count()),
            Stat::make('Séances à venir', Commission::query()->where('status', 'scheduled')->count()),
            Stat::make('En délibération', Commission::query()->where('status', 'in_progress')->count()),
            Stat::make('Décisions à valider', \App\Models\CommissionDecision::query()->where('status', 'pending_validation')->count()),
        ];
    }

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('commissions.manage');
    }
}