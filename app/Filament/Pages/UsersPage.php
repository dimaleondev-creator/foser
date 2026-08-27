<?php

namespace App\Filament\Pages;

use UnitEnum;

class UsersPage extends SectionPage
{
    protected static ?string $title = 'Utilisateurs';
    protected static ?string $navigationLabel = 'Etudiants, chercheurs et agents';
    protected static string|UnitEnum|null $navigationGroup = 'UTILISATEURS';
    protected static string $module = 'Utilisateurs';
    protected static array $permissions = ['users.view'];
    protected static array $items = ['Etudiants', 'Chercheurs', 'Universites', 'Agents'];
}
