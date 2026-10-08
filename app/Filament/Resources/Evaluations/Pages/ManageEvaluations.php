<?php
namespace App\Filament\Resources\Evaluations\Pages;
use App\Filament\Resources\Evaluations\EvaluationResource;
use Filament\Resources\Pages\ManageRecords;
class ManageEvaluations extends ManageRecords { protected static string $resource=EvaluationResource::class; protected function getHeaderActions():array{return [];} }
