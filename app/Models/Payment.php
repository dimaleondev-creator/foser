<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model { use HasUuids; protected $table='payment_records'; protected $fillable=['disbursement_id','beneficiary_id','provider_reference','payment_method','amount','status','paid_at','processed_by','comment','executed_at','cancelled_at']; protected function casts():array{return ['paid_at'=>'datetime','executed_at'=>'datetime','cancelled_at'=>'datetime','amount'=>'decimal:2'];} protected static function booted():void { static::deleting(function(self $model):void { if(in_array($model->status,['valide','execute','annule'],true)) throw new \LogicException('Une opération financière validée ne peut pas être supprimée.'); }); } }
