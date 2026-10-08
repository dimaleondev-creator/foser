<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Media\Pages\ManageMedia;
use App\Models\Media;
use App\Models\MediaAlbum;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class MediaResource extends BaseCrudResource
{
    protected static ?string $model = Media::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Médias';
    protected static string $permission = 'content.view';
    protected static ?string $managePermission = 'content.update';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre')->required()->maxLength(255),
            Select::make('media_type')->label('Type')->options(['image' => 'Image', 'video' => 'Vidéo'])->required()->live(),
            Select::make('album_id')->label('Album')->options(fn (): array => MediaAlbum::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
            FileUpload::make('path')->label('Fichier')->disk('public')->directory('media')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm'])->maxSize(51200)->required(fn (Get $get): bool => blank($get('external_url'))),
            TextInput::make('external_url')->label('URL vidéo externe')->url()->maxLength(2000)->visible(fn (Get $get): bool => $get('media_type') === 'video')->required(fn (Get $get): bool => $get('media_type') === 'video' && blank($get('path'))),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Titre')->searchable()->sortable(),
            TextColumn::make('media_type')->label('Type')->badge(),
            TextColumn::make('album.name')->label('Album')->searchable(),
            TextColumn::make('mime_type')->label('Format')->toggleable(),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('created_at')->label('Ajouté le')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('media_type')->options(['image' => 'Image', 'video' => 'Vidéo']),
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
            SelectFilter::make('album_id')->label('Album')->options(fn (): array => MediaAlbum::query()->orderBy('name')->pluck('name', 'id')->all()),
        ])->recordActions([
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMedia::route('/')];
    }
}
