<?php

namespace App\Filament\Resources\CmsContents;

use App\Filament\Resources\CmsContents\Pages\ManageCmsContents;
use App\Models\CmsContent;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CmsContentResource extends Resource
{
    protected static ?string $model = CmsContent::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION';
    protected static ?string $navigationLabel = 'Contenus institutionnels';
    protected static ?string $recordTitleAttribute = 'content_key';

    public static function canAccess(): bool { return Gate::allows('content.view'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('content_key')->label('Clé de contenu')->required()->maxLength(100),
            Select::make('locale')->label('Langue')->options(['fr' => 'Français', 'en' => 'Anglais'])->default('fr')->required(),
            TextInput::make('title')->label('Titre')->maxLength(255),
            Textarea::make('summary')->label('Résumé')->columnSpanFull(),
            Textarea::make('body')->label('Contenu')->rows(12)->columnSpanFull(),
            Select::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
            DateTimePicker::make('published_at')->label('Date de publication'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('content_key')->label('Clé')->searchable()->sortable(),
            TextColumn::make('locale')->label('Langue')->badge(),
            TextColumn::make('title')->label('Titre')->searchable(),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('author.name')->label('Auteur'),
            TextColumn::make('updated_at')->label('Modifié le')->dateTime()->sortable(),
        ])->recordActions([
            EditAction::make()->visible(fn () => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn () => Gate::allows('content.delete')),
        ]);
    }

    public static function getPages(): array { return ['index' => ManageCmsContents::route('/')]; }
}
