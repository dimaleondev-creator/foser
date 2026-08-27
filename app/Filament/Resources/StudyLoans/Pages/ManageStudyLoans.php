<?php
namespace App\Filament\Resources\StudyLoans\Pages;
use App\Filament\Resources\StudyLoans\StudyLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageStudyLoans extends ManageRecords { protected static string $resource = StudyLoanResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn()=>Gate::allows('programs.create'))]; } }
