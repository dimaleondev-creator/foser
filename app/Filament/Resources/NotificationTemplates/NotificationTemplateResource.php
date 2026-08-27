<?php

namespace App\Filament\Resources\NotificationTemplates;

use App\Filament\Resources\NotificationTemplates\Pages\ManageNotificationTemplates;
use App\Models\NotificationTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';
    protected static string|UnitEnum|null $navigationGroup = 'COMMUNICATION UTILISATEURS';
    protected static ?string $navigationLabel = 'Modèles de notifications';
    public static function canAccess(): bool { return Gate::allows('settings.manage'); }
    public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('event')->required(),Select::make('channel')->options(['internal'=>'Interne','email'=>'Email','sms'=>'SMS'])->required(),TextInput::make('subject'),Textarea::make('body')->required()->columnSpanFull(),Toggle::make('active')->default(true)]); }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('event')->searchable(),TextColumn::make('channel')->badge(),TextColumn::make('subject')->searchable(),TextColumn::make('active')->label('Actif')->formatStateUsing(fn (bool $state): string => $state ? 'Oui' : 'Non')->badge()->color(fn (bool $state): string => $state ? 'success' : 'gray'),TextColumn::make('updated_at')->dateTime()])->recordActions([EditAction::make()->visible(fn()=>Gate::allows('settings.manage')),DeleteAction::make()->visible(fn()=>Gate::allows('settings.manage'))]); }
    public static function getPages(): array { return ['index' => ManageNotificationTemplates::route('/')]; }
}
