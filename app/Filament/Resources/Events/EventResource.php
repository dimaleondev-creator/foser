<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Events\Pages\ManageEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class EventResource extends BaseCrudResource
{
	protected static ?string $model = Event::class;
	protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
	protected static ?string $navigationLabel = 'Événements';
	protected static string $permission = 'content.view';

	public static function form(Schema $schema): Schema
	{
		return $schema->components([
			TextInput::make('title')->label('Titre')->required()->maxLength(255),
			TextInput::make('slug')->label('Identifiant URL')->required()->unique(ignoreRecord: true)->maxLength(255),
			TextInput::make('category')->label('Catégorie')->maxLength(80),
			Textarea::make('description')->label('Description')->maxLength(20000)->columnSpanFull(),
			TextInput::make('venue')->label('Lieu')->maxLength(255),
			DateTimePicker::make('starts_at')->label('Début')->required(),
			DateTimePicker::make('ends_at')->label('Fin')->after('starts_at'),
			FileUpload::make('image_path')->label('Image')->disk('public')->directory('events')->image()->maxSize(5120),
			TextInput::make('external_url')->label('Lien externe')->url()->maxLength(255),
			TextInput::make('registration_url')->label('Lien d’inscription')->url()->maxLength(255),
			TextInput::make('capacity')->label('Capacité')->numeric()->minValue(1),
			Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
			Toggle::make('is_featured')->label('À la une'),
		]);
	}

	public static function table(Table $table): Table
	{
		return $table->columns([
			TextColumn::make('title')->label('Événement')->searchable()->sortable()->limit(60),
			TextColumn::make('category')->label('Catégorie')->searchable()->sortable(),
			TextColumn::make('venue')->label('Lieu')->searchable(),
			TextColumn::make('starts_at')->label('Début')->dateTime('d/m/Y H:i')->sortable(),
			TextColumn::make('ends_at')->label('Fin')->dateTime('d/m/Y H:i')->sortable(),
			TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'published' => 'success', 'archived' => 'gray', default => 'warning' }),
		])->filters([
			SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
			SelectFilter::make('category')->options(fn (): array => Event::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all()),
		])->recordActions([
			EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
			DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
		])->defaultSort('starts_at');
	}

	public static function getPages(): array
	{
		return ['index' => ManageEvents::route('/')];
	}
}
