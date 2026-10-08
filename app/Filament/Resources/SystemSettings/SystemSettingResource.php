<?php

namespace App\Filament\Resources\SystemSettings;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\SystemSettings\Pages\ManageSystemSettings;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

class SystemSettingResource extends BaseCrudResource
{
	protected static ?string $model = SystemSetting::class;
	protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
	protected static ?string $navigationLabel = 'Paramètres du site';
	protected static string|UnitEnum|null $navigationGroup = 'Divers';
	protected static string $permission = 'settings.manage';
	protected static array $fields = [
		['name' => 'key', 'required' => true],
		['name' => 'value', 'type' => 'textarea'],
		['name' => 'type', 'required' => true],
		['name' => 'is_public', 'type' => 'select', 'options' => [true => 'Oui', false => 'Non']],
	];

	public static function form(Schema $schema): Schema
	{
		return $schema->components([
			TextInput::make('key')
				->label('Clé du réglage')
				->required()
				->unique(ignoreRecord: true)
				->maxLength(255)
				->live()
				->helperText('Utilisez « site_logo » pour le logo général ou « divers » pour la photo de la Directrice. Modifiez la ligne existante pour remplacer un fichier.'),
			Select::make('type')
				->label('Type de contenu')
				->options(['image' => 'Image', 'string' => 'Texte'])
				->default('image')
				->required()
				->live(),
			FileUpload::make('value')
				->label('Image du réglage')
				->disk('public')
				->directory(fn (Get $get): string => $get('key') === 'site_logo' ? 'site' : 'divers')
				->image()
				->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
				->maxSize(5120)
				->helperText('Clé « site_logo » : logo général du site. Clé « divers » : portrait de la Directrice.' )
				->visible(fn (Get $get): bool => $get('type') === 'image'),
			Textarea::make('value')
				->label('Valeur')
				->visible(fn (Get $get): bool => $get('type') !== 'image'),
			Toggle::make('is_public')
				->label('Afficher sur le site public')
				->default(true),
		]);
	}

	public static function getPages(): array
	{
		return ['index' => ManageSystemSettings::route('/')];
	}
}
