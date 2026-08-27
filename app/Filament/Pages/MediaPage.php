<?php

namespace App\Filament\Pages;

use UnitEnum;

class MediaPage extends SectionPage
{
    protected static ?string $title = 'Mediatheque';
    protected static ?string $navigationLabel = 'Mediatheque';
    protected static string|UnitEnum|null $navigationGroup = 'MEDIATHEQUE';
    protected static string $module = 'Mediatheque';
    protected static array $permissions = ['content.view'];
    protected static array $items = ['Albums', 'Photos', 'Videos'];
}
