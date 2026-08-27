<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Events\Pages\ManageEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

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
			TextInput::make('slug')->label('Identifiant URL')->required()->maxLength(255),
			TextInput::make('category')->label('Catégorie')->maxLength(80),
			Textarea::make('description')->label('Description')->columnSpanFull(),
			TextInput::make('venue')->label('Lieu')->maxLength(255),
			DateTimePicker::make('starts_at')->label('Début')->required(),
			DateTimePicker::make('ends_at')->label('Fin')->after('starts_at'),
			FileUpload::make('image_path')->label('Image')->disk('public')->directory('events')->image()->maxSize(5120),
			TextInput::make('external_url')->label('Lien externe')->url()->maxLength(255),
			TextInput::make('registration_url')->label('Lien d’inscription')->url()->maxLength(255),
			TextInput::make('capacity')->label('Capacité')->numeric()->minValue(1),
			TextInput::make('status')->label('Statut')->default('draft')->required()->maxLength(30),
			Toggle::make('is_featured')->label('À la une'),
		]);
	}

	public static function getPages(): array
	{
		return ['index' => ManageEvents::route('/')];
	}
}
