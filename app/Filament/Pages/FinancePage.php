<?php

namespace App\Filament\Pages;

use UnitEnum;

class FinancePage extends SectionPage
{
    protected static ?string $title = 'Finances';
    protected static ?string $navigationLabel = 'Finances';
    protected static string|UnitEnum|null $navigationGroup = 'FINANCES';
    protected static string $module = 'Finances';
    protected static array $permissions = ['finance.view'];
    protected static array $items = ['Engagements', 'Décaissements', 'Paiements'];
}
