<?php

namespace App\Filament\Pages;

use UnitEnum;

class DossiersPage extends SectionPage
{
    protected static ?string $title = 'Dossiers';
    protected static ?string $navigationLabel = 'Dossiers et documents';
    protected static string|UnitEnum|null $navigationGroup = 'DOSSIERS';
    protected static string $module = 'Dossiers';
    protected static array $permissions = ['applications.view', 'documents.view'];
    protected static array $items = ['Dossiers', 'Documents', 'Pieces manquantes', 'Historique'];
}
