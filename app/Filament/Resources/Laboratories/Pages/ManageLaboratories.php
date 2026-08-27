<?php
namespace App\Filament\Resources\Laboratories\Pages;
use App\Filament\Resources\Laboratories\LaboratoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageLaboratories extends ManageRecords { protected static string $resource = LaboratoryResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn()=>Gate::allows('research.manage'))]; } }
