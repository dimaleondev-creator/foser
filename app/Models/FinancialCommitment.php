<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FinancialCommitment extends Model { use HasUuids, SoftDeletes; protected $fillable=['application_id','research_project_id','program_id','beneficiary_id','reference','amount','budget','currency','fiscal_year','status','committed_at','processed_by','comment','executed_at','cancelled_at']; protected function casts():array{return ['committed_at'=>'date','executed_at'=>'datetime','cancelled_at'=>'datetime','amount'=>'decimal:2','budget'=>'decimal:2'];} protected static function booted():void { static::deleting(function(self $model):void { if(in_array($model->status,['valide','execute','annule'],true)) throw new \LogicException('Une opération financière validée ne peut pas être supprimée.'); }); } }
