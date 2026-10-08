<?php

namespace App\Filament\Resources\Commissions\Pages;

use App\Filament\Resources\Commissions\CommissionResource;
use App\Services\CommissionWorkflowService;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCommission extends CreateRecord
{
    protected static string $resource = CommissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(CommissionWorkflowService::class)->validateContext($data);
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        app(CommissionWorkflowService::class)->created($actor, $this->record);
    }
}