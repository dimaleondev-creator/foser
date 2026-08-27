<?php

namespace App\Filament\Pages;

use UnitEnum;

class CallsPage extends SectionPage
{
    protected static ?string $title = 'Appels et candidatures';
    protected static ?string $navigationLabel = 'Appels et candidatures';
    protected static string|UnitEnum|null $navigationGroup = 'APPELS';
    protected static string $module = 'Appels';
    protected static array $permissions = ['calls.view', 'applications.view', 'evaluations.view'];
    protected static array $items = ['Appels a candidatures', 'Candidatures', 'Evaluations', 'Resultats'];
}
