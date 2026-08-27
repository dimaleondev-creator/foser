<?php

namespace App\Filament\Resources\Universities;

use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Models\University;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class UniversityResource extends Resource
{
    protected static ?string $model = University::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'ÉTABLISSEMENTS';

    protected static ?string $navigationLabel = 'Universités';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->account_type, ['super_admin', 'admin'], true)
            && Gate::allows('university.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('short_name')
                    ->label('Nom court')
                    ->maxLength(80),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(80)
                    ->unique(ignoreRecord: true),
                TextInput::make('country')
                    ->label('Pays')
                    ->required()
                    ->maxLength(100),
                TextInput::make('city')
                    ->label('Ville')
                    ->maxLength(120),
                TextInput::make('website')
                    ->label('Site web')
                    ->url()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type d’université')
                    ->options([
                        'public' => 'Publique',
                        'private' => 'Privée',
                    ])
                    ->default('public')
                    ->required(),
                Select::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('short_name')->label('Nom court')->searchable(),
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('country')->label('Pays')->sortable(),
                TextColumn::make('city')->label('Ville')->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => $state === 'private' ? 'Privée' : 'Publique')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'private' ? 'warning' : 'info')
                    ->sortable(),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('updated_at')->label('Modifiée le')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUniversities::route('/'),
        ];
    }
}
