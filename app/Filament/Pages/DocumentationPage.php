<?php

namespace App\Filament\Pages;

use UnitEnum;

class DocumentationPage extends SectionPage
{
    protected static ?string $title = 'Documentation';
    protected static ?string $navigationLabel = 'Documentation';
    protected static string|UnitEnum|null $navigationGroup = 'DOCUMENTATION';
    protected static string $module = 'Documentation';
    protected static array $permissions = ['documents.view'];
    protected static array $items = ['Documents', 'Categories', 'Telechargements'];
}
