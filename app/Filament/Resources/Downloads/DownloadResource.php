<?php

namespace App\Filament\Resources\Downloads;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Downloads\Pages\ManageDownloads;
use App\Models\Download;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DownloadResource extends BaseCrudResource
{
    protected static ?string $model = Download::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Téléchargements';
    protected static string|UnitEnum|null $navigationGroup = 'DOCUMENTATION';
    protected static string $permission = 'documents.view';
    protected static ?string $managePermission = 'documents.update';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('document.title')->label('Document')->searchable()->sortable(),
            TextColumn::make('user.name')->label('Utilisateur')->searchable(),
            TextColumn::make('ip_address')->label('Adresse IP')->searchable(),
            TextColumn::make('downloaded_at')->label('Date')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('document_id')->label('Document')->relationship('document', 'title')->searchable()->preload(),
        ])->recordActions([])->defaultSort('downloaded_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDownloads::route('/')];
    }
}
