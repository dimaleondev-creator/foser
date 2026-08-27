<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;

class NewsletterSubscriberResource extends BaseCrudResource
{
    protected static ?string $model = NewsletterSubscriber::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Abonnés newsletter';
    protected static string $permission = 'content.view';
    protected static ?string $managePermission = 'content.update';
    protected static array $fields = [
        ['name' => 'name'],
        ['name' => 'email', 'required' => true],
        ['name' => 'status', 'required' => true],
        ['name' => 'source'],
    ];

    public static function getPages(): array
    {
        return ['index' => ManageNewsletterSubscribers::route('/')];
    }
}