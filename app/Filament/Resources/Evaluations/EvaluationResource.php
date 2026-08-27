<?php
namespace App\Filament\Resources\Evaluations;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Evaluations\Pages\ManageEvaluations;
use App\Models\Evaluation;
use BackedEnum;
class EvaluationResource extends BaseCrudResource { protected static ?string $model=Evaluation::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-clipboard-document-check'; protected static ?string $navigationLabel='Évaluations'; protected static string $permission='evaluations.view'; protected static array $fields=[['name'=>'application_id','required'=>true],['name'=>'evaluator_id','required'=>true],['name'=>'status','required'=>true],['name'=>'comment','type'=>'textarea']]; public static function getPages():array{return ['index'=>ManageEvaluations::route('/')];} }
