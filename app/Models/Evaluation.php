<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Evaluation extends Model { use HasUuids; protected $fillable=['application_id','evaluator_id','status','comment','submitted_at']; protected function casts():array{return ['submitted_at'=>'datetime'];} public function application(){return $this->belongsTo(Application::class);} public function evaluator(){return $this->belongsTo(User::class,'evaluator_id');} }
