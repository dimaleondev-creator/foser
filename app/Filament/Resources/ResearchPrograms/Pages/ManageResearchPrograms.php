<?php
namespace App\Filament\Resources\ResearchPrograms\Pages;
use App\Filament\Resources\ResearchPrograms\ResearchProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageResearchPrograms extends ManageRecords { protected static string $resource = ResearchProgramResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn()=>Gate::allows('research.manage'))]; } }
