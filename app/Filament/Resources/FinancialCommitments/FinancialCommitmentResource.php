<?php
namespace App\Filament\Resources\FinancialCommitments;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\FinancialCommitments\Pages\ManageFinancialCommitments;
use App\Models\FinancialCommitment;
use BackedEnum;
class FinancialCommitmentResource extends BaseCrudResource { protected static ?string $model=FinancialCommitment::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-banknotes'; protected static ?string $navigationLabel='Engagements financiers'; protected static string $permission='finance.view'; protected static array $fields=[['name'=>'reference','required'=>true],['name'=>'application_id'],['name'=>'research_project_id'],['name'=>'amount','type'=>'number','required'=>true],['name'=>'currency','required'=>true],['name'=>'status','required'=>true],['name'=>'committed_at','type'=>'date','required'=>true]]; public static function getPages():array{return ['index'=>ManageFinancialCommitments::route('/')];} }
