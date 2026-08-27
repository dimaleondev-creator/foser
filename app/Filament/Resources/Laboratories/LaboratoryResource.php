<?php

namespace App\Filament\Resources\Laboratories;

use App\Filament\Resources\Laboratories\Pages\ManageLaboratories;
use App\Models\Laboratory;
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

class LaboratoryResource extends Resource
{
    protected static ?string $model = Laboratory::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static string|UnitEnum|null $navigationGroup = 'RECHERCHE';
    protected static ?string $navigationLabel = 'Laboratoires';
    public static function canAccess(): bool { return Gate::allows('research.view'); }
    public static function form(Schema $schema): Schema { return $schema->components([Select::make('university_id')->label('Université')->options(fn():array=>DB::table('universities')->orderBy('name')->pluck('name','id')->all()),TextInput::make('name')->required()->maxLength(255),TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(80),Textarea::make('description')->columnSpanFull(),Select::make('status')->options(['active'=>'Actif','inactive'=>'Inactif'])->default('active')->required()]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable()->sortable(),TextColumn::make('code')->searchable(),TextColumn::make('university.name')->label('Université')->searchable(),TextColumn::make('status')->badge()])->recordActions([EditAction::make()->visible(fn()=>Gate::allows('research.manage')),DeleteAction::make()->visible(fn()=>Gate::allows('research.manage'))]); }
    public static function getPages(): array { return ['index'=>ManageLaboratories::route('/')]; }
}
