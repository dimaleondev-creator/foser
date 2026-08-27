<?php
namespace App\Filament\Resources\Payments;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use BackedEnum;
class PaymentResource extends BaseCrudResource { protected static ?string $model=Payment::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-currency-dollar'; protected static ?string $navigationLabel='Paiements'; protected static string $permission='finance.view'; protected static array $fields=[['name'=>'disbursement_id','required'=>true],['name'=>'provider_reference'],['name'=>'payment_method'],['name'=>'amount','type'=>'number','required'=>true],['name'=>'status','required'=>true],['name'=>'paid_at','type'=>'date']]; public static function getPages():array{return ['index'=>ManagePayments::route('/')];} }
