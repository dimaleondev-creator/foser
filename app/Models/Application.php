<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Application extends Model { use HasUuids, SoftDeletes; protected $fillable=['call_id','program_id','applicant_id','reference','status','submitted_at','decided_at','applicant_note','project_title','summary','description','domain','objectives','methodology','calendar','budget','team']; protected function casts():array{return ['submitted_at'=>'datetime','decided_at'=>'datetime','budget'=>'decimal:2'];} public function applicant(){return $this->belongsTo(User::class,'applicant_id');} public function program(){return $this->belongsTo(Program::class);} }
