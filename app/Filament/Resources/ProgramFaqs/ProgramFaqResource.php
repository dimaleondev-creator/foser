<?php

namespace App\Filament\Resources\ProgramFaqs;

use App\Filament\Resources\ProgramFaqs\Pages\ManageProgramFaqs;
use App\Models\ProgramFaq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ProgramFaqResource extends Resource
{
    protected static ?string $model = ProgramFaq::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static string|UnitEnum|null $navigationGroup = 'PROGRAMMES';
    protected static ?string $navigationLabel = 'Questions fréquentes';

    public static function canAccess(): bool
    {
        return Gate::allows('programs.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('program_id')->label('Programme')->options(fn (): array => DB::table('programs')->orderBy('name')->pluck('name', 'id')->all())->searchable()->nullable(),
            Select::make('category')->label('Catégorie')->options(['aides-financieres' => 'Aides financières', 'prets-etudes' => 'Prêts d’études', 'recherche' => 'Recherche', 'innovation' => 'Innovation', 'general' => 'Général'])->required(),
            TextInput::make('question')->label('Question')->required()->maxLength(255),
            Textarea::make('answer')->label('Réponse')->required()->columnSpanFull(),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié'])->default('published')->required(),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('question')->label('Question')->searchable()->sortable(),
            TextColumn::make('category')->label('Catégorie')->badge(),
            TextColumn::make('program.name')->label('Programme')->placeholder('-')->searchable(),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('sort_order')->label('Ordre')->sortable(),
        ])->recordActions([
            EditAction::make()->visible(fn (): bool => Gate::allows('programs.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('programs.delete')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProgramFaqs::route('/')];
    }
}
