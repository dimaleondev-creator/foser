<?php

namespace App\Filament\Resources\ResearchPrograms;

use App\Filament\Resources\ResearchPrograms\Pages\ManageResearchPrograms;
use App\Models\ResearchProgram;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ResearchProgramResource extends Resource
{
    protected static ?string $model = ResearchProgram::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static string|UnitEnum|null $navigationGroup = 'RECHERCHE';
    protected static ?string $navigationLabel = 'Programmes de recherche';
    public static function canAccess(): bool { return Gate::allows('research.view'); }
    public static function form(Schema $schema): Schema { return $schema->components([Select::make('program_id')->label('Programme')->options(fn():array=>DB::table('programs')->orderBy('name')->pluck('name','id')->all())->required(),TextInput::make('research_area')->label('Domaine de recherche')->required()->maxLength(255),TextInput::make('maturity_level')->label('Niveau de maturité')->maxLength(40),Textarea::make('description')->label('Description')->columnSpanFull(),Textarea::make('beneficiaries')->label('Bénéficiaires')->columnSpanFull(),Textarea::make('establishments')->label('Établissements concernés')->columnSpanFull(),Textarea::make('conditions')->label('Conditions d’éligibilité')->columnSpanFull(),TextInput::make('minimum_amount')->label('Montant minimal')->numeric()->minValue(0),TextInput::make('maximum_amount')->label('Montant maximal')->numeric()->minValue(0),TextInput::make('duration')->label('Durée'),TextInput::make('calendar')->label('Calendrier'),DatePicker::make('opens_at')->label('Date d’ouverture'),DatePicker::make('closes_at')->label('Date de clôture'),TextInput::make('contact')->label('Contact'),TextInput::make('document_path')->label('Document / règlement'),Select::make('status')->label('Statut')->options(['active'=>'Actif','inactive'=>'Inactif'])->default('active')->required()]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('program.name')->label('Programme')->searchable(),TextColumn::make('research_area')->searchable()->sortable(),TextColumn::make('maturity_level')->badge(),TextColumn::make('updated_at')->dateTime()])->recordActions([EditAction::make()->visible(fn()=>Gate::allows('research.manage')),DeleteAction::make()->visible(fn()=>Gate::allows('research.manage'))]); }
    public static function getPages(): array { return ['index'=>ManageResearchPrograms::route('/')]; }
}
