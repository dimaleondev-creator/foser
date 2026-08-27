<?php
namespace App\Filament\Resources\NotificationTemplates\Pages;
use App\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageNotificationTemplates extends ManageRecords { protected static string $resource=NotificationTemplateResource::class; protected function getHeaderActions():array{return [CreateAction::make()->visible(fn()=>Gate::allows('settings.manage'))];} }
