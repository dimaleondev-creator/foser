<?php

namespace App\Filament\Resources\InstitutionValues\Pages;

use App\Filament\Resources\InstitutionValues\InstitutionValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageInstitutionValues extends ManageRecords
{
    protected static string $resource = InstitutionValueResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn (): bool => Gate::allows('content.create'))]; }
}