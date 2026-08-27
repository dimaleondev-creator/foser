<?php
namespace App\Filament\Resources\FinancialAids\Pages;
use App\Filament\Resources\FinancialAids\FinancialAidResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;
class ManageFinancialAids extends ManageRecords { protected static string $resource = FinancialAidResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->visible(fn()=>Gate::allows('programs.create'))]; } }
