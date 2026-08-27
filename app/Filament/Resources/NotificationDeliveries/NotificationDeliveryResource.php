<?php

namespace App\Filament\Resources\NotificationDeliveries;

use App\Filament\Resources\NotificationDeliveries\Pages\ManageNotificationDeliveries;
use App\Models\NotificationDelivery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class NotificationDeliveryResource extends Resource
{
    protected static ?string $model = NotificationDelivery::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION UTILISATEURS';
    protected static ?string $navigationLabel = 'Historique notifications';
    public static function canAccess(): bool { return Gate::allows('notifications.send'); }
    public static function form(Schema $schema): Schema { return $schema->components([]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('user.email')->label('Destinataire')->searchable(),TextColumn::make('event')->label('Type')->searchable(),TextColumn::make('channel')->label('Canal')->badge(),TextColumn::make('status')->label('Statut')->badge(),TextColumn::make('attempts')->label('Tentatives'),TextColumn::make('last_error')->label('Erreur')->limit(60),TextColumn::make('sent_at')->label('Envoyée le')->dateTime(),TextColumn::make('created_at')->dateTime()->sortable()]); }
    public static function getPages(): array { return ['index' => ManageNotificationDeliveries::route('/')]; }
}
