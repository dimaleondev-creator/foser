<?php

namespace App\Filament\Resources\DocumentCategories;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\DocumentCategories\Pages\ManageDocumentCategories;
use App\Models\DocumentCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class DocumentCategoryResource extends BaseCrudResource
{
    protected static ?string $model = DocumentCategory::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Catégories documentaires';
    protected static string|UnitEnum|null $navigationGroup = 'DOCUMENTATION';
    protected static string $permission = 'documents.view';
    protected static array $fields = [
        ['name' => 'name', 'required' => true, 'max' => 120],
        ['name' => 'slug', 'required' => true, 'max' => 120],
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(120)->unique(ignoreRecord: true),
            TextInput::make('slug')->label('Identifiant URL')->required()->alphaDash()->maxLength(120)->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nom')->searchable()->sortable(),
            TextColumn::make('slug')->label('Slug')->searchable(),
            TextColumn::make('documents_count')->label('Documents')->counts('documents')->sortable(),
            TextColumn::make('created_at')->label('Créée le')->dateTime()->sortable(),
        ])->filters([TrashedFilter::make()])->recordActions([
            EditAction::make()->authorize('documents.update')->visible(fn (): bool => Gate::allows('documents.update')),
            DeleteAction::make()->authorize('documents.delete')->visible(fn (): bool => Gate::allows('documents.delete')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDocumentCategories::route('/')];
    }
}
