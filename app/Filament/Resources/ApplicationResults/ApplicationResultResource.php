<?php
namespace App\Filament\Resources\ApplicationResults;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\ApplicationResults\Pages\ManageApplicationResults;
use App\Models\ApplicationResult;
use BackedEnum;
class ApplicationResultResource extends BaseCrudResource { protected static ?string $model=ApplicationResult::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-check-badge'; protected static ?string $navigationLabel='Résultats'; protected static string $permission='applications.view'; protected static array $fields=[['name'=>'application_id','required'=>true],['name'=>'decided_by'],['name'=>'decision','required'=>true],['name'=>'score','type'=>'number'],['name'=>'reason','type'=>'textarea']]; public static function getPages():array{return ['index'=>ManageApplicationResults::route('/')];} }
