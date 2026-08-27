<?php

namespace App\Filament\Resources\ProgramFaqs\Pages;

use App\Filament\Resources\ProgramFaqs\ProgramFaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageProgramFaqs extends ManageRecords
{
    protected static string $resource = ProgramFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => Gate::allows('programs.create'))];
    }
}
