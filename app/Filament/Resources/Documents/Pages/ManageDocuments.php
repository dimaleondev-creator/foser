<?php
namespace App\Filament\Resources\Documents\Pages;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageDocuments extends ManageRecords
{
	protected static string $resource = DocumentResource::class;

	protected function getHeaderActions(): array
	{
		return [CreateAction::make()->authorize('documents.upload')->visible(fn (): bool => Gate::allows('documents.upload'))];
	}
}
