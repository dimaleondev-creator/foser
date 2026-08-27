<?php

namespace App\Filament\Resources\OrganizationUnits\Pages;

use App\Filament\Resources\OrganizationUnits\OrganizationUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageOrganizationUnits extends ManageRecords
{
    protected static string $resource = OrganizationUnitResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn (): bool => Gate::allows('content.create'))]; }
}
