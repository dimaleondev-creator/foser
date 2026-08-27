<?php

namespace App\Filament\Pages;

use UnitEnum;

class UserCommunicationPage extends SectionPage
{
    protected static ?string $title = 'Communication utilisateurs';
    protected static ?string $navigationLabel = 'Communication utilisateurs';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION UTILISATEURS';
    protected static string $module = 'Communication utilisateurs';
    protected static array $permissions = ['notifications.send', 'content.view'];
    protected static array $items = ['Notifications', 'Messagerie', 'Reclamations'];
}
