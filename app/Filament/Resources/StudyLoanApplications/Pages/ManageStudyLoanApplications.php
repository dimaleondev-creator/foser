<?php

namespace App\Filament\Resources\StudyLoanApplications\Pages;

use App\Filament\Resources\StudyLoanApplications\StudyLoanApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageStudyLoanApplications extends ManageRecords
{
    protected static string $resource = StudyLoanApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => Gate::allows('finance.manage'))];
    }
}
