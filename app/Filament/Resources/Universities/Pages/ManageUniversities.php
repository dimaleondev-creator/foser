<?php

namespace App\Filament\Resources\Universities\Pages;

use App\Filament\Resources\Universities\UniversityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageUniversities extends ManageRecords
{
    protected static string $resource = UniversityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajouter une université')
                ->visible(fn (): bool => Gate::allows('university.manage')),
        ];
    }
}
