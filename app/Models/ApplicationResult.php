<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ApplicationResult extends Model { use HasUuids; protected $fillable=['application_id','decided_by','decision','score','reason','published_at']; protected function casts():array{return ['published_at'=>'datetime','score'=>'decimal:2'];} public function application(){return $this->belongsTo(Application::class);} }
