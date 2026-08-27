<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Partners\Pages\ManagePartners;
use App\Models\Partner;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

class PartnerResource extends BaseCrudResource
{
    protected static ?string $model = Partner::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Partenaires';
    protected static string $permission = 'content.view';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('slug')->label('Identifiant URL')->required()->maxLength(255),
            FileUpload::make('logo_path')->label('Logo')->disk('public')->directory('partners')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            TextInput::make('type')->label('Type')->maxLength(80),
            TextInput::make('category')->label('Catégorie')->maxLength(80),
            TextInput::make('website_url')->label('Site web')->url()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->maxLength(255),
            TextInput::make('phone')->label('Téléphone')->maxLength(40),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
            TextInput::make('status')->label('Statut')->default('draft')->required()->maxLength(30),
            Toggle::make('is_featured')->label('À la une'),
            DatePicker::make('starts_at')->label('Début'),
            DatePicker::make('ends_at')->label('Fin')->afterOrEqual('starts_at'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartners::route('/')];
    }
}
