<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Partners\Pages\ManagePartners;
use App\Models\Partner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PartnerResource extends BaseCrudResource
{
    protected static ?string $model = Partner::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Partenaires';
    protected static string|UnitEnum|null $navigationGroup = 'Divers';
    protected static string $permission = 'content.view';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('slug')->label('Identifiant URL')->required()->unique(ignoreRecord: true)->maxLength(255),
            FileUpload::make('logo_path')->label('Logo')->disk('public')->directory('partners')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120),
            Textarea::make('description')->label('Description')->maxLength(5000)->columnSpanFull(),
            TextInput::make('type')->label('Type')->maxLength(80),
            TextInput::make('category')->label('Catégorie')->maxLength(80),
            TextInput::make('website_url')->label('Site web')->url()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->maxLength(255),
            TextInput::make('phone')->label('Téléphone')->maxLength(40),
            TextInput::make('sort_order')->label('Ordre')->numeric()->integer()->minValue(0)->default(0),
            Select::make('display_location')
                ->label('Emplacement sur le site')
                ->options(['footer' => 'Pied de page', 'home' => "Section partenaires de l'accueil", 'both' => 'Accueil et pied de page'])
                ->default('footer')
                ->required(),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
            Toggle::make('is_featured')->label('À la une'),
            DatePicker::make('starts_at')->label('Début'),
            DatePicker::make('ends_at')->label('Fin')->afterOrEqual('starts_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Partenaire')->searchable()->sortable(),
            TextColumn::make('type')->label('Type')->searchable(),
            TextColumn::make('category')->label('Catégorie')->searchable(),
            TextColumn::make('display_location')->label('Emplacement')->badge(),
            TextColumn::make('sort_order')->label('Ordre')->sortable(),
            TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'published' => 'success', 'archived' => 'gray', default => 'warning' }),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
            SelectFilter::make('display_location')->label('Emplacement')->options(['footer' => 'Pied de page', 'home' => "Accueil", 'both' => 'Accueil et pied de page']),
        ])->recordActions([
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartners::route('/')];
    }
}
