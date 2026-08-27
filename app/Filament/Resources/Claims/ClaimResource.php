<?php
namespace App\Filament\Resources\Claims;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Claims\Pages\ManageClaims;
use App\Models\Claim;
use BackedEnum;
class ClaimResource extends BaseCrudResource { protected static ?string $model=Claim::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-exclamation-triangle'; protected static ?string $navigationLabel='Réclamations'; protected static string $permission='applications.view'; protected static array $fields=[['name'=>'claimant_id','required'=>true],['name'=>'application_id'],['name'=>'reference','required'=>true],['name'=>'subject','required'=>true],['name'=>'description','type'=>'textarea','required'=>true],['name'=>'status','required'=>true],['name'=>'assigned_to'],['name'=>'resolved_at','type'=>'date']]; public static function getPages():array{return ['index'=>ManageClaims::route('/')];} }
