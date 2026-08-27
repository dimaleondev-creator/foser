<?php

namespace App\Filament\Resources\Programs;

use App\Filament\Resources\Programs\Pages\ManagePrograms;
use App\Models\Program;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|UnitEnum|null $navigationGroup = 'PROGRAMMES';
    protected static ?string $navigationLabel = 'Programmes';
    protected static ?string $recordTitleAttribute = 'name';
    public static function canAccess(): bool { return Gate::allows('programs.view'); }
    public static function form(Schema $schema): Schema { return $schema->components([
        TextInput::make('name')->required()->maxLength(255), TextInput::make('code')->required()->unique(ignoreRecord: true),
        Select::make('type')->options(['education'=>'Éducation','research'=>'Recherche','innovation'=>'Innovation'])->required(),
        Textarea::make('description')->columnSpanFull(), TextInput::make('budget')->numeric()->minValue(0), TextInput::make('currency')->default('GNF')->length(3),
        DatePicker::make('starts_at'), DatePicker::make('ends_at'), Select::make('status')->options(['draft'=>'Brouillon','published'=>'Publié','closed'=>'Clôturé'])->required(),
    ]); }
    public static function table(Table $table): Table { return $table->columns([
        TextColumn::make('name')->searchable()->sortable(), TextColumn::make('code')->searchable(), TextColumn::make('type')->badge(), TextColumn::make('status')->badge(), TextColumn::make('budget')->money('GNF'), TextColumn::make('updated_at')->dateTime()->sortable(),
    ])->recordActions([EditAction::make()->visible(fn()=>Gate::allows('programs.update')), DeleteAction::make()->visible(fn()=>Gate::allows('programs.delete'))]); }
    public static function getPages(): array { return ['index'=>ManagePrograms::route('/')]; }
}
