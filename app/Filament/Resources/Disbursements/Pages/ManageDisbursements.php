<?php
namespace App\Filament\Resources\Disbursements\Pages;
use App\Filament\Resources\Disbursements\DisbursementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
class ManageDisbursements extends ManageRecords { protected static string $resource=DisbursementResource::class; protected function getHeaderActions():array{return [CreateAction::make()->visible(fn()=>auth()->user()?->can('finance.manage')??false)];} }
