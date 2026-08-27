<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Disbursement extends Model { use HasUuids; protected $fillable=['commitment_id','reference','amount','status','scheduled_for','disbursed_at','processed_by','comment','executed_at','cancelled_at']; protected function casts():array{return ['scheduled_for'=>'date','disbursed_at'=>'date','executed_at'=>'datetime','cancelled_at'=>'datetime','amount'=>'decimal:2'];} protected static function booted():void { static::deleting(function(self $model):void { if(in_array($model->status,['valide','execute','annule'],true)) throw new \LogicException('Une opération financière validée ne peut pas être supprimée.'); }); } }
