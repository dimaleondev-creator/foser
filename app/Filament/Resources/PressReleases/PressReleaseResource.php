<?php

namespace App\Filament\Resources\PressReleases;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\PressReleases\Pages\ManagePressReleases;
use App\Models\PressRelease;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PressReleaseResource extends BaseCrudResource
{
    protected static ?string $model = PressRelease::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Communiqués';
    protected static string $permission = 'content.view';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre')->required()->maxLength(255),
            TextInput::make('slug')->label('Identifiant URL')->required()->unique(ignoreRecord: true)->maxLength(255),
            Select::make('author_id')->label('Auteur')->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())->default(fn () => Auth::id())->searchable()->preload(),
            RichEditor::make('body')->label('Contenu')->required()->maxLength(50000)->columnSpanFull(),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
            DateTimePicker::make('published_at')->label('Date de publication')->seconds(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Titre')->searchable()->sortable()->limit(60),
            TextColumn::make('author.name')->label('Auteur')->searchable(),
            TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'published' => 'success', 'archived' => 'gray', default => 'warning' }),
            TextColumn::make('published_at')->label('Publication')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('updated_at')->label('Modifié le')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
        ])->recordActions([
            Action::make('publish')->label('Publier')->color('success')->requiresConfirmation()->visible(fn (PressRelease $record): bool => Gate::allows('content.publish') && $record->status !== 'published')->action(fn (PressRelease $record) => $record->forceFill(['status' => 'published', 'published_at' => $record->published_at ?: now()])->save()),
            Action::make('unpublish')->label('Dépublier')->color('warning')->requiresConfirmation()->visible(fn (PressRelease $record): bool => Gate::allows('content.publish') && $record->status === 'published')->action(fn (PressRelease $record) => $record->forceFill(['status' => 'draft'])->save()),
            Action::make('archive')->label('Archiver')->color('gray')->requiresConfirmation()->visible(fn (PressRelease $record): bool => Gate::allows('content.publish') && $record->status !== 'archived')->action(fn (PressRelease $record) => $record->forceFill(['status' => 'archived'])->save()),
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ])->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePressReleases::route('/')];
    }
}