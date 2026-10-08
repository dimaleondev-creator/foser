<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use App\Services\ContactMessageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION';
    protected static ?string $navigationLabel = 'Messages de contact';

    public static function canAccess(): bool
    {
        return Gate::allows('communication.private.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Reçu le')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('name')->label('Nom')->searchable(),
            TextColumn::make('email')->label('Email')->searchable(),
            TextColumn::make('subject')->label('Objet')->searchable()->limit(60),
            TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'closed' => 'gray', 'replied' => 'success', 'read' => 'info', default => 'warning' }),
            TextColumn::make('replies_count')->counts('replies')->label('Réponses'),
        ])->filters([
            SelectFilter::make('status')->options(['new' => 'Nouveau', 'read' => 'Lu', 'replied' => 'Répondu', 'closed' => 'Fermé']),
        ])->recordActions([
            Action::make('conversation')->label('Consulter')->icon('heroicon-o-chat-bubble-left-right')->modalHeading(fn (ContactMessage $record): string => $record->subject)->modalContent(fn (ContactMessage $record) => view('filament.communication.contact-history', ['contact' => $record->load('replies')]))->modalSubmitAction(false)->modalCancelActionLabel('Fermer')->visible(fn (): bool => Gate::allows('communication.private.view')),
            Action::make('reply')->label('Répondre')->color('primary')->schema([Textarea::make('body')->label('Réponse')->required()->maxLength(10000)->rows(8)])->action(fn (ContactMessage $record, array $data) => app(ContactMessageService::class)->reply($record, $data['body']))->visible(fn (ContactMessage $record): bool => Gate::allows('content.update') && $record->status !== 'closed'),
            Action::make('markRead')->label('Marquer lu')->action(fn (ContactMessage $record) => app(ContactMessageService::class)->setStatus($record, 'read'))->visible(fn (ContactMessage $record): bool => Gate::allows('content.update') && $record->status === 'new'),
            Action::make('close')->label('Clore')->color('gray')->requiresConfirmation()->action(fn (ContactMessage $record) => app(ContactMessageService::class)->setStatus($record, 'closed'))->visible(fn (ContactMessage $record): bool => Gate::allows('content.update') && $record->status !== 'closed'),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageContactMessages::route('/')];
    }
}
