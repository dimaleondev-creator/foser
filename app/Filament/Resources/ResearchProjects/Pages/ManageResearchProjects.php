<?php

namespace App\Filament\Resources\ResearchProjects\Pages;

use App\Filament\Resources\ResearchProjects\ResearchProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResearchProjects extends ManageRecords
{
    protected static string $resource = ResearchProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
