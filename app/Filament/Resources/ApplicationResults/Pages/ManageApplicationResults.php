<?php
namespace App\Filament\Resources\ApplicationResults\Pages;
use App\Filament\Resources\ApplicationResults\ApplicationResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
class ManageApplicationResults extends ManageRecords { protected static string $resource=ApplicationResultResource::class; protected function getHeaderActions():array{return [CreateAction::make()->visible(fn()=>auth()->user()?->can('applications.validate')??false)];} }
