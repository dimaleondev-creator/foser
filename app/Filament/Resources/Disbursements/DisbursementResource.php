<?php
namespace App\Filament\Resources\Disbursements;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Disbursements\Pages\ManageDisbursements;
use App\Models\Disbursement;
use BackedEnum;
class DisbursementResource extends BaseCrudResource { protected static ?string $model=Disbursement::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-arrow-up-right'; protected static ?string $navigationLabel='Décaissements'; protected static string $permission='finance.view'; protected static array $fields=[['name'=>'commitment_id','required'=>true],['name'=>'reference','required'=>true],['name'=>'amount','type'=>'number','required'=>true],['name'=>'status','required'=>true],['name'=>'scheduled_for','type'=>'date'],['name'=>'disbursed_at','type'=>'date']]; public static function getPages():array{return ['index'=>ManageDisbursements::route('/')];} }
