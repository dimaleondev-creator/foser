<?php

namespace App\Filament\Resources\SystemSettings\Pages;

use App\Filament\Resources\SystemSettings\SystemSettingResource;
use App\Filament\Resources\Partners\PartnerResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageSystemSettings extends ManageRecords
{
	protected static string $resource = SystemSettingResource::class;

	protected function getHeaderActions(): array
	{
		return [
			Action::make('managePartners')
				->label('Gérer les partenaires')
				->icon('heroicon-o-building-office-2')
				->url(PartnerResource::getUrl('index'))
				->visible(fn (): bool => Gate::allows('content.view')),
			CreateAction::make()
				->label('Ajouter un réglage')
				->visible(fn (): bool => Gate::allows('settings.manage')),
		];
	}
}
