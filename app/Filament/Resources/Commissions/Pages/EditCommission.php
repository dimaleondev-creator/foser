<?php

namespace App\Filament\Resources\Commissions\Pages;

use App\Filament\Resources\Commissions\CommissionResource;
use App\Services\CommissionWorkflowService;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EditCommission extends EditRecord
{
    protected static string $resource = CommissionResource::class;

    private array $beforeUpdate = [];

    protected function beforeSave(): void
    {
        Gate::authorize('update', $this->record);
        abort_unless($this->record->status === 'draft' || ($this->record->status === 'scheduled' && ! $this->record->convocation_sent_at), 422);
        $this->beforeUpdate = $this->record->getAttributes();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(CommissionWorkflowService::class)->validateContext($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        app(CommissionWorkflowService::class)->updated($actor, $this->record, $this->beforeUpdate);
    }
}