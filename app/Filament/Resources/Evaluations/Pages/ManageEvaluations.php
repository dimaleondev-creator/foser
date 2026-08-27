<?php
namespace App\Filament\Resources\Evaluations\Pages;
use App\Filament\Resources\Evaluations\EvaluationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
class ManageEvaluations extends ManageRecords { protected static string $resource=EvaluationResource::class; protected function getHeaderActions():array{return [CreateAction::make()->visible(fn()=>auth()->user()?->can('evaluations.create')??false)];} }
