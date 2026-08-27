<?php

namespace App\Filament\Resources\OrganizationUnits;

use App\Filament\Resources\OrganizationUnits\Pages\ManageOrganizationUnits;
use App\Models\OrganizationUnit;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class OrganizationUnitResource extends Resource
{
    protected static ?string $model = OrganizationUnit::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';
    protected static string|UnitEnum|null $navigationGroup = 'INSTITUTION';
    protected static ?string $navigationLabel = 'Organigramme';
    protected static ?string $recordTitleAttribute = 'name_fr';

    public static function canAccess(): bool { return Gate::allows('content.view'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_fr')->label('Nom (français)')->required()->maxLength(255),
            TextInput::make('name_en')->label('Nom (anglais)')->maxLength(255),
            Select::make('parent_id')->label('Parent')->options(fn (?OrganizationUnit $record): array => OrganizationUnit::query()->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))->orderBy('name_fr')->pluck('name_fr', 'id')->all())->searchable()->nullable(),
            Select::make('responsible_id')->label('Responsable')->options(fn (): array => DB::table('users')->orderBy('name')->pluck('name', 'id')->all())->searchable()->nullable(),
            Select::make('unit_type')->label('Type de structure')->options(['board' => 'Conseil d’administration', 'general_management' => 'Direction générale', 'direction' => 'Direction technique', 'service' => 'Service'])->default('direction')->required(),
            TextInput::make('acronym')->label('Sigle')->maxLength(30),
            TextInput::make('function_fr')->label('Fonction (français)'), TextInput::make('function_en')->label('Fonction (anglais)'),
            Textarea::make('description_fr')->label('Description')->columnSpanFull(),
            TextInput::make('professional_email')->label('Email professionnel')->email(), TextInput::make('professional_phone')->label('Téléphone professionnel'),
            Textarea::make('biography_fr')->label('Biographie (français)')->columnSpanFull(), Textarea::make('biography_en')->label('Biographie (anglais)')->columnSpanFull(),
            FileUpload::make('photo_path')->label('Photo')->disk('public')->directory('organization')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)->imageEditor(),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0)->minValue(0)->required(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name_fr')->label('Structure')->searchable()->sortable(),
            TextColumn::make('parent.name_fr')->label('Parent')->searchable(),
            TextColumn::make('responsible.name')->label('Responsable')->searchable(),
            TextColumn::make('unit_type')->label('Type')->badge(),
            TextColumn::make('sort_order')->label('Ordre')->sortable(),
            TextColumn::make('is_active')->label('Active')->formatStateUsing(fn (bool $state): string => $state ? 'Oui' : 'Non')->badge()->color(fn (bool $state): string => $state ? 'success' : 'gray'),
        ])->defaultSort('sort_order')->recordActions([
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ]);
    }

    public static function getPages(): array { return ['index' => ManageOrganizationUnits::route('/')]; }
}
