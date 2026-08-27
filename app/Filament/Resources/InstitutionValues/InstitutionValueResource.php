<?php

namespace App\Filament\Resources\InstitutionValues;

use App\Filament\Resources\InstitutionValues\Pages\ManageInstitutionValues;
use App\Models\InstitutionValue;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class InstitutionValueResource extends Resource
{
    protected static ?string $model = InstitutionValue::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';
    protected static string|UnitEnum|null $navigationGroup = 'INSTITUTION';
    protected static ?string $navigationLabel = 'Valeurs institutionnelles';

    public static function canAccess(): bool { return Gate::allows('content.view'); }
    public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('name')->label('Nom')->required(), Textarea::make('description')->label('Description')->required()->columnSpanFull(), TextInput::make('icon')->label('Icône'), TextInput::make('sort_order')->label('Ordre')->numeric()->default(0), Toggle::make('is_active')->label('Active')->default(true)]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->label('Nom')->searchable()->sortable(), TextColumn::make('sort_order')->label('Ordre')->sortable(), TextColumn::make('is_active')->label('Active')->badge()])->recordActions([EditAction::make()->visible(fn (): bool => Gate::allows('content.update')), DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete'))]); }
    public static function getPages(): array { return ['index' => ManageInstitutionValues::route('/')]; }
}