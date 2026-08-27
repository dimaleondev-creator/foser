<?php

namespace App\Filament\Pages;

use UnitEnum;

class ResearchPage extends SectionPage
{
    protected static ?string $title = 'Recherche';
    protected static ?string $navigationLabel = 'Recherche et publications';
    protected static string|UnitEnum|null $navigationGroup = 'RECHERCHE';
    protected static string $module = 'Recherche';
    protected static array $permissions = ['research.view'];
    protected static array $items = ['Projets', 'Chercheurs', 'Laboratoires', 'Publications'];
}
