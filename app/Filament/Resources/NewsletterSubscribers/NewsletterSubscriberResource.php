<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class NewsletterSubscriberResource extends BaseCrudResource
{
    protected static ?string $model = NewsletterSubscriber::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Abonnés newsletter';
    protected static string $permission = 'content.view';

    public static function canAccess(): bool
    {
        return Gate::allows('communication.private.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->maxLength(255),
            TextInput::make('email')->label('Adresse courriel')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            Select::make('status')->label('Statut')->options(['pending' => 'En attente de confirmation', 'active' => 'Actif', 'inactive' => 'Désinscrit'])->default('pending')->required(),
            TextInput::make('source')->label('Origine')->maxLength(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('email')->label('Adresse courriel')->searchable()->sortable(),
            TextColumn::make('name')->label('Nom')->searchable(),
            TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'active' => 'success', 'inactive' => 'gray', default => 'warning' }),
            TextColumn::make('source')->label('Origine'),
            TextColumn::make('confirmed_at')->label('Confirmé le')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('created_at')->label('Inscrit le')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'En attente', 'active' => 'Actif', 'inactive' => 'Désinscrit']),
        ])->recordActions([
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsletterSubscribers::route('/')];
    }
}