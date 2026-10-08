<?php

namespace App\Filament\Pages;

use UnitEnum;

class CommunicationPage extends SectionPage
{
    protected static ?string $title = 'Communication';
    protected static ?string $navigationLabel = 'Communication';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION';
    protected static string $module = 'Communication';
    protected static array $permissions = ['content.view', 'calls.view'];
    protected static array $items = [
        'Actualités et articles',
        'Communiqués',
        'Événements',
        'Galeries photos',
        'Vidéos',
        'Témoignages',
        'Partenaires',
        'Abonnés newsletter',
        'Campagnes newsletter',
        'Pages institutionnelles',
        'Messages de contact',
    ];
}
