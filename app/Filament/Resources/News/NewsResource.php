<?php

namespace App\Filament\Resources\News;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\News\Pages\ManageNews;
use App\Models\MediaAlbum;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use App\Services\NewsPublicationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class NewsResource extends BaseCrudResource
{
    protected static ?string $model = News::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'Actualités';
    protected static string $permission = 'content.view';
    protected static ?string $managePermission = 'content.update';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(255),
            Textarea::make('excerpt')->label('Résumé')->rows(3)->maxLength(1000)->columnSpanFull(),
            RichEditor::make('body')->label('Contenu')->required()->columnSpanFull(),
            FileUpload::make('image_path')->label('Image principale')->disk('public')->directory('news')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120),
            Select::make('category_id')->label('Catégorie')->options(fn (): array => NewsCategory::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
            Select::make('media_album_id')->label('Galerie associée')->options(fn (): array => MediaAlbum::query()->where('status', 'published')->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
            Select::make('author_id')->label('Auteur')->options(fn (): array => User::query()->whereIn('account_type', ['admin', 'directeur_general', 'agent_communication'])->orderBy('name')->pluck('name', 'id')->all())->default(fn () => auth()->id())->searchable()->preload(),
            Select::make('visibility')->label('Visibilité')->options(['public' => 'Publique', 'internal' => 'Interne'])->default('public')->required(),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
            DateTimePicker::make('published_at')->label('Date de publication')->seconds(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Titre')->searchable()->sortable()->limit(60),
            TextColumn::make('category.name')->label('Catégorie')->searchable(),
            TextColumn::make('author.name')->label('Auteur')->searchable(),
            TextColumn::make('visibility')->label('Visibilité')->badge(),
            TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'published' => 'success', 'archived' => 'gray', default => 'warning' }),
            TextColumn::make('published_at')->label('Publication')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('updated_at')->label('Modifiée')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
            SelectFilter::make('visibility')->options(['public' => 'Publique', 'internal' => 'Interne']),
            SelectFilter::make('category_id')->label('Catégorie')->options(fn (): array => NewsCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
        ])->recordActions([
            Action::make('publish')->label('Publier')->color('success')->requiresConfirmation()->visible(fn (News $record): bool => Gate::allows('content.publish') && $record->status !== 'published')->action(fn (News $record) => app(NewsPublicationService::class)->publish($record)),
            Action::make('unpublish')->label('Dépublier')->color('warning')->requiresConfirmation()->visible(fn (News $record): bool => Gate::allows('content.publish') && $record->status === 'published')->action(fn (News $record) => app(NewsPublicationService::class)->unpublish($record)),
            Action::make('archive')->label('Archiver')->color('gray')->requiresConfirmation()->visible(fn (News $record): bool => Gate::allows('content.publish') && $record->status !== 'archived')->action(fn (News $record) => app(NewsPublicationService::class)->archive($record)),
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNews::route('/')];
    }
}
