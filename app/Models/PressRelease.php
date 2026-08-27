<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class PressRelease extends Model { use HasUuids,SoftDeletes; protected $table='press_releases'; protected $fillable=['author_id','title','slug','body','status','published_at']; protected function casts():array{return ['published_at'=>'datetime'];} }
