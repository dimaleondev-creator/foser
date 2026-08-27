<?php

namespace App\Filament\Pages;

use UnitEnum;

class SystemPage extends SectionPage
{
    protected static ?string $title = 'Systeme';
    protected static ?string $navigationLabel = 'Securite et configuration';
    protected static string|UnitEnum|null $navigationGroup = 'SYSTEME';
    protected static string $module = 'Systeme';
    protected static array $permissions = ['audit.view', 'settings.manage', 'users.view'];
    protected static array $items = ['Audit', 'Parametres', 'Roles', 'Permissions'];
}
