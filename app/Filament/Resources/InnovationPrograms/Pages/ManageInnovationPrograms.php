<?php
namespace App\Filament\Resources\InnovationPrograms\Pages;
use App\Filament\Resources\InnovationPrograms\InnovationProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageInnovationPrograms extends ManageRecords { protected static string $resource = InnovationProgramResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn()=>Gate::allows('research.manage'))]; } }
