<?php

namespace App\Filament\Resources\Publications;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Models\ResearchPublication;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PublicationResource extends BaseCrudResource
{
    protected static ?string $model = ResearchPublication::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Publications';
    protected static string|UnitEnum|null $navigationGroup = 'RECHERCHE';
    protected static string $permission = 'research.view';
    protected static array $fields = [];

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre')->required()->maxLength(255),
            Select::make('research_project_id')->label('Projet')->relationship('project', 'title')->searchable()->preload(),
            Select::make('author_id')->label('Auteur')->relationship('author', 'name')->searchable()->preload(),
            TextInput::make('doi')->label('DOI')->maxLength(120),
            TextInput::make('publication_type')->label('Type')->required()->maxLength(40),
            DatePicker::make('published_on')->label('Date de publication'),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'submitted' => 'Soumise', 'validated' => 'Validée', 'rejected' => 'Rejetée'])->required(),
            Select::make('document_id')->label('Document associé')->relationship('document', 'title')->searchable()->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Titre')->searchable()->sortable(),
            TextColumn::make('project.title')->label('Projet')->searchable(),
            TextColumn::make('author.name')->label('Auteur')->searchable(),
            TextColumn::make('publication_type')->label('Type')->searchable(),
            TextColumn::make('status')->label('Statut')->badge()->sortable(),
            TextColumn::make('published_on')->label('Publication')->date()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'submitted' => 'Soumise', 'validated' => 'Validée', 'rejected' => 'Rejetée']),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePublications::route('/')];
    }
}
