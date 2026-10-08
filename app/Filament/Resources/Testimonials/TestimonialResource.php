<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Program;
use App\Models\Testimonial;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class TestimonialResource extends BaseCrudResource
{
    protected static ?string $model = Testimonial::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Témoignages';
    protected static string $permission = 'content.view';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('first_name')->label('Prénom')->required()->maxLength(255),
            TextInput::make('last_name')->label('Nom')->required()->maxLength(255),
            TextInput::make('job_title')->label('Fonction')->maxLength(255),
            TextInput::make('organization')->label('Organisme')->maxLength(255),
            FileUpload::make('photo_path')->label('Photo')->disk('public')->directory('testimonials')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120),
            Textarea::make('body')->label('Témoignage')->required()->maxLength(10000)->columnSpanFull(),
            Select::make('program_id')->label('Programme')->options(fn (): array => Program::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
            TextInput::make('rating')->label('Note')->numeric()->minValue(1)->maxValue(5),
            Select::make('status')->label('Statut')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'])->default('draft')->required(),
            TextInput::make('sort_order')->label('Ordre')->numeric()->integer()->minValue(0)->default(0),
            DateTimePicker::make('published_at')->label('Date de publication'),
            Toggle::make('consent_given')->label('Consentement de publication')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('display_name')->label('Témoin')->searchable(['first_name', 'last_name'])->sortable(),
            TextColumn::make('job_title')->label('Fonction')->searchable(),
            TextColumn::make('organization')->label('Organisme')->searchable(),
            TextColumn::make('rating')->label('Note')->sortable(),
            IconColumn::make('consent_given')->label('Consentement')->boolean(),
            TextColumn::make('status')->label('Statut')->badge()->color(fn (string $state): string => match ($state) { 'published' => 'success', 'archived' => 'gray', default => 'warning' }),
            TextColumn::make('published_at')->label('Publication')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
            SelectFilter::make('consent_given')->label('Consentement')->options([1 => 'Accord donné', 0 => 'En attente']),
        ])->recordActions([
            EditAction::make()->visible(fn (): bool => Gate::allows('content.update')),
            DeleteAction::make()->visible(fn (): bool => Gate::allows('content.delete')),
        ])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
