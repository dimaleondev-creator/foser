<?php

namespace App\Filament\Resources\InnovationPrograms;

use App\Filament\Resources\InnovationPrograms\Pages\ManageInnovationPrograms;
use App\Models\InnovationProgram;
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

class InnovationProgramResource extends Resource
{
    protected static ?string $model = InnovationProgram::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';
    protected static string|UnitEnum|null $navigationGroup = 'RECHERCHE';
    protected static ?string $navigationLabel = 'Programmes d’innovation';
    public static function canAccess(): bool { return Gate::allows('research.view'); }
    public static function form(Schema $schema): Schema { return $schema->components([Select::make('program_id')->label('Programme')->options(fn():array=>DB::table('programs')->orderBy('name')->pluck('name','id')->all())->required(),TextInput::make('innovation_area')->label('Domaine d’innovation')->required()->maxLength(255),Select::make('innovation_type')->label('Dispositif')->options(['contest'=>'Concours','incubation'=>'Incubation','valorization'=>'Valorisation'])->default('incubation')->required(),TextInput::make('target_stage')->label('Stade ciblé')->maxLength(40),Textarea::make('description')->label('Description')->columnSpanFull(),Textarea::make('objectives')->label('Objectifs')->columnSpanFull(),Textarea::make('target_audience')->label('Public cible')->columnSpanFull(),Textarea::make('eligibility')->label('Critères d’éligibilité')->columnSpanFull(),Textarea::make('support')->label('Accompagnement')->columnSpanFull(),Textarea::make('phases')->label('Phases')->columnSpanFull(),Textarea::make('training')->label('Formations')->columnSpanFull(),Textarea::make('mentoring')->label('Mentorat')->columnSpanFull(),Textarea::make('technical_support')->label('Accompagnement technique')->columnSpanFull(),Textarea::make('entrepreneurial_support')->label('Accompagnement entrepreneurial')->columnSpanFull(),Textarea::make('partners')->label('Partenaires')->columnSpanFull(),Textarea::make('resources')->label('Ressources')->columnSpanFull(),TextInput::make('duration')->label('Durée'),TextInput::make('calendar')->label('Calendrier'),DatePicker::make('opens_at')->label('Date d’ouverture'),DatePicker::make('closes_at')->label('Date limite'),DatePicker::make('proclamation_at')->label('Date de proclamation'),Textarea::make('prizes')->label('Prix et récompenses')->columnSpanFull(),Textarea::make('technology')->label('Technologie')->columnSpanFull(),Textarea::make('results')->label('Résultats')->columnSpanFull(),Textarea::make('patents')->label('Brevets')->columnSpanFull(),Textarea::make('procedure')->label('Procédure et candidature')->columnSpanFull(),TextInput::make('contact')->label('Contact'),Select::make('status')->label('Statut')->options(['active'=>'Actif','inactive'=>'Inactif'])->default('active')->required()]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('program.name')->label('Programme')->searchable(),TextColumn::make('innovation_area')->searchable()->sortable(),TextColumn::make('target_stage')->badge(),TextColumn::make('updated_at')->dateTime()])->recordActions([EditAction::make()->visible(fn()=>Gate::allows('research.manage')),DeleteAction::make()->visible(fn()=>Gate::allows('research.manage'))]); }
    public static function getPages(): array { return ['index'=>ManageInnovationPrograms::route('/')]; }
}
