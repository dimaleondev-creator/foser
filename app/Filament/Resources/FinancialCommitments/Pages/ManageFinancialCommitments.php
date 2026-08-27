<?php
namespace App\Filament\Resources\FinancialCommitments\Pages;
use App\Filament\Resources\FinancialCommitments\FinancialCommitmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
class ManageFinancialCommitments extends ManageRecords { protected static string $resource=FinancialCommitmentResource::class; protected function getHeaderActions():array{return [CreateAction::make()->visible(fn()=>auth()->user()?->can('finance.manage')??false)];} }
