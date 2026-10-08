<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AIAssistantPage extends Page
{
    protected string $view = 'filament.pages.ai-assistant';
    protected static ?string $title = 'Assistant IA FOSER';
    protected static ?string $navigationLabel = 'Assistant IA';
    protected static string|UnitEnum|null $navigationGroup = 'SYSTÈME';

    public static function canAccess(): bool
    {
        return Gate::allows('view_statistics');
    }
}
