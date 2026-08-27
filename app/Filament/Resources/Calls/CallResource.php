<?php

namespace App\Filament\Resources\Calls;

use App\Filament\Resources\Calls\Pages\ManageCalls;
use App\Models\Call;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CallResource extends Resource
{
    protected static ?string $model = Call::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static string|UnitEnum|null $navigationGroup = 'APPELS';
    protected static ?string $navigationLabel = 'Appels à candidatures';
    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return Gate::allows('calls.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Appel')->schema([
                Select::make('program_id')->label('Programme')->options(fn (): array => DB::table('programs')->orderBy('name')->pluck('name', 'id')->all())->required(),
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('reference')->required()->unique(ignoreRecord: true),
                Textarea::make('description')->columnSpanFull(),
                Textarea::make('objectives')->label('Objectifs')->columnSpanFull(),
                Textarea::make('domains')->label('Domaines concernés')->columnSpanFull(),
                Textarea::make('beneficiaries')->label('Bénéficiaires')->columnSpanFull(),
                Textarea::make('conditions')->label('Conditions d’éligibilité')->columnSpanFull(),
                Textarea::make('required_documents')->label('Documents obligatoires')->helperText('Un document par ligne.')->columnSpanFull(),
                TextInput::make('places')->numeric()->minValue(1),
                TextInput::make('amount')->numeric()->minValue(0),
                TextInput::make('available_budget')->label('Budget disponible')->numeric()->minValue(0),
                TextInput::make('maximum_project_amount')->label('Montant maximal par projet')->numeric()->minValue(0),
                TextInput::make('currency')->default('GNF')->length(3),
                DatePicker::make('published_at')->label('Date de publication'),
                DatePicker::make('opens_at')->required(),
                DatePicker::make('closes_at')->required()->after('opens_at'),
                TextInput::make('contact')->label('Contact'),
                TextInput::make('application_url')->label('Lien de candidature')->url(),
                TextInput::make('regulation_path')->label('Règlement'),
                TextInput::make('terms_path')->label('Termes de référence'),
                Select::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'suspended' => 'Suspendu', 'closed' => 'Clôturé'])->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable(),
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('opens_at')->date(),
            TextColumn::make('closes_at')->date(),
            TextColumn::make('places'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCalls::route('/')];
    }
}