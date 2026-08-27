<?php

namespace App\Filament\Resources\CmsContents\Pages;

use App\Filament\Resources\CmsContents\CmsContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageCmsContents extends ManageRecords
{
    protected static string $resource = CmsContentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => Gate::allows('content.create'))];
    }
}
