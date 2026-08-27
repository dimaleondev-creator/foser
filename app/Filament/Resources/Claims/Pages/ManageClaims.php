<?php
namespace App\Filament\Resources\Claims\Pages;
use App\Filament\Resources\Claims\ClaimResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
class ManageClaims extends ManageRecords { protected static string $resource=ClaimResource::class; protected function getHeaderActions():array{return [CreateAction::make()->visible(fn()=>auth()->user()?->can('applications.create')??false)];} }
