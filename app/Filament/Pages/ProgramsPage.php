<?php

namespace App\Filament\Pages;

use UnitEnum;

class ProgramsPage extends SectionPage
{
    protected static ?string $title = 'Programmes';
    protected static ?string $navigationLabel = 'Programmes et financements';
    protected static string|UnitEnum|null $navigationGroup = 'PROGRAMMES';
    protected static string $module = 'Programmes';
    protected static array $permissions = ['programs.view', 'finance.view', 'research.view'];
    protected static array $items = ['Programmes', 'Aides financieres', "Prets d'etudes", 'Recherche', 'Innovation'];
}
