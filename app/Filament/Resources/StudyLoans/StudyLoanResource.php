<?php

namespace App\Filament\Resources\StudyLoans;

use App\Filament\Resources\StudyLoans\Pages\ManageStudyLoans;
use App\Models\StudyLoan;
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

class StudyLoanResource extends Resource
{
    protected static ?string $model = StudyLoan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|UnitEnum|null $navigationGroup = 'PROGRAMMES';
    protected static ?string $navigationLabel = 'Prêts d’études';
    public static function canAccess(): bool { return Gate::allows('programs.view'); }
    public static function form(Schema $schema): Schema { return $schema->components([Select::make('program_id')->label('Programme')->options(fn():array=>DB::table('programs')->orderBy('name')->pluck('name','id')->all())->required(),TextInput::make('name')->required(),TextInput::make('maximum_amount')->label('Montant maximal')->numeric()->minValue(0),TextInput::make('interest_rate')->label('Taux annuel (%)')->numeric()->minValue(0),TextInput::make('repayment_months')->label('Durée maximale (mois)')->numeric()->minValue(1),Textarea::make('description')->label('Informations complémentaires')->columnSpanFull(),Textarea::make('eligibility')->label('Critères d’éligibilité')->columnSpanFull(),Textarea::make('beneficiaries')->label('Bénéficiaires')->columnSpanFull(),Textarea::make('study_levels')->label('Niveaux d’études')->columnSpanFull(),Textarea::make('required_documents')->label('Pièces justificatives')->columnSpanFull(),Textarea::make('procedure')->label('Procédure')->columnSpanFull(),Select::make('status')->options(['active'=>'Actif','inactive'=>'Inactif'])->default('active')->required()]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable()->sortable(),TextColumn::make('program.name')->label('Programme'),TextColumn::make('maximum_amount')->money('XOF'),TextColumn::make('interest_rate')->suffix(' %'),TextColumn::make('status')->badge()])->recordActions([EditAction::make()->visible(fn()=>Gate::allows('programs.update')),DeleteAction::make()->visible(fn()=>Gate::allows('programs.delete'))]); }
    public static function getPages(): array { return ['index'=>ManageStudyLoans::route('/')]; }
}
