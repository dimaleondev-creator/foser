<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Claim extends Model { use HasUuids, SoftDeletes; protected $fillable=['claimant_id','application_id','reference','subject','description','status','assigned_to','resolved_at']; protected function casts():array{return ['resolved_at'=>'datetime'];} public function claimant(){return $this->belongsTo(User::class,'claimant_id');} }
