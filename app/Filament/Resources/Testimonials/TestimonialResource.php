<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

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
            Textarea::make('body')->label('Témoignage')->required()->columnSpanFull(),
            TextInput::make('program_id')->label('Programme')->maxLength(36),
            TextInput::make('rating')->label('Note')->numeric()->minValue(1)->maxValue(5),
            TextInput::make('status')->label('Statut')->default('draft')->required()->maxLength(30),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
            DateTimePicker::make('published_at')->label('Date de publication'),
            Toggle::make('consent_given')->label('Consentement de publication')->required(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
