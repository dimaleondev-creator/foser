<?php

namespace App\Filament\Resources\MediaAlbums;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\MediaAlbums\Pages\ManageMediaAlbums;
use App\Models\MediaAlbum;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class MediaAlbumResource extends BaseCrudResource
{
	protected static ?string $model = MediaAlbum::class;
	protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
	protected static ?string $navigationLabel = 'Albums média';
	protected static string $permission = 'content.view';
	protected static ?string $managePermission = 'content.update';

	public static function form(Schema $schema): Schema
	{
		return $schema->components([
			TextInput::make('name')->label('Nom de l’album')->required()->maxLength(255),
			TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(255),
			Textarea::make('description')->label('Description')->maxLength(5000)->columnSpanFull(),
			Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
		]);
	}

	public static function table(Table $table): Table
	{
		return $table->columns([
			TextColumn::make('name')->label('Album')->searchable()->sortable(),
			TextColumn::make('slug')->label('Slug')->searchable(),
			TextColumn::make('media_count')->counts('media')->label('Médias'),
			TextColumn::make('status')->label('Statut')->badge(),
			TextColumn::make('updated_at')->dateTime('d/m/Y H:i')->sortable(),
		])->filters([
			SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
		])->recordActions([
			EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
			DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
		]);
	}

	public static function getPages(): array
	{
		return ['index' => ManageMediaAlbums::route('/')];
	}
}
