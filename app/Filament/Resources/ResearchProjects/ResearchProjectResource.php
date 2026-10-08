<?php

namespace App\Filament\Resources\ResearchProjects;

use App\Filament\Resources\ResearchProjects\Pages\ManageResearchProjects;
use App\Models\ResearchProject;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ResearchProjectResource extends Resource
{
    protected static ?string $model = ResearchProject::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static string|UnitEnum|null $navigationGroup = 'RECHERCHE';
    protected static ?string $navigationLabel = 'Projets de recherche';
    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return Gate::allows('research.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Projet')->schema([
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('reference')->required()->unique(ignoreRecord: true),
                Textarea::make('abstract')->columnSpanFull(),
                Textarea::make('description')->label('Description')->columnSpanFull(),
                Textarea::make('expected_results')->label('Résultats attendus')->columnSpanFull(),
                Textarea::make('achieved_results')->label('Résultats obtenus')->columnSpanFull(),
                TextInput::make('domain')->label('Domaine'),
                TextInput::make('year')->label('Année')->numeric(),
                TextInput::make('budget')->numeric()->minValue(0),
                TextInput::make('funded_amount')->label('Montant financé')->numeric()->minValue(0),
                TextInput::make('currency')->default('FCFA')->length(4),
                Select::make('status')->options(['draft' => 'Brouillon', 'submitted' => 'Soumis', 'under_review' => 'En évaluation', 'accepted' => 'Accepté', 'rejected' => 'Rejeté', 'funded' => 'Financé'])->required(),
                DatePicker::make('starts_at'),
                DatePicker::make('ends_at'),
                TextInput::make('contact')->label('Contact'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable(),
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('budget')->money('FCFA'),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageResearchProjects::route('/')];
    }
}
