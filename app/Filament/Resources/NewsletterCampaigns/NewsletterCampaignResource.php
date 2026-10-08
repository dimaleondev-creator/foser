<?php

namespace App\Filament\Resources\NewsletterCampaigns;

use App\Filament\Resources\NewsletterCampaigns\Pages\ManageNewsletterCampaigns;
use App\Models\NewsletterCampaign;
use App\Services\NewsletterCampaignService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION';
    protected static ?string $navigationLabel = 'Campagnes newsletter';

    public static function canAccess(): bool
    {
        return Gate::allows('communication.private.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Nom de la campagne')->required()->maxLength(255),
            TextInput::make('subject')->label('Objet du courriel')->required()->maxLength(255),
            Textarea::make('body')->label('Contenu du courriel')->required()->maxLength(50000)->rows(16)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Campagne')->searchable()->sortable(),
            TextColumn::make('subject')->label('Objet')->searchable()->limit(60),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('recipient_count')->label('Destinataires'),
            TextColumn::make('sent_count')->label('Envoyés'),
            TextColumn::make('failed_count')->label('Échecs'),
            TextColumn::make('sent_at')->label('Envoyée le')->dateTime('d/m/Y H:i'),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'sending' => 'En cours', 'sent' => 'Envoyée', 'partially_sent' => 'Partiellement envoyée', 'failed' => 'Échec']),
        ])->recordActions([
            Action::make('send')->label('Envoyer aux abonnés confirmés')->color('primary')->requiresConfirmation()->modalDescription('L’envoi est irréversible et cible les abonnés actifs ayant confirmé leur adresse.')->action(fn (NewsletterCampaign $record) => app(NewsletterCampaignService::class)->send($record))->visible(fn (NewsletterCampaign $record): bool => Gate::allows('content.publish') && $record->status === 'draft'),
            EditAction::make()->visible(fn (NewsletterCampaign $record): bool => Gate::allows('content.update') && $record->status === 'draft'),
            DeleteAction::make()->visible(fn (NewsletterCampaign $record): bool => Gate::allows('content.delete') && $record->status === 'draft'),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsletterCampaigns::route('/')];
    }
}
