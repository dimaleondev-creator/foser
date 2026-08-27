<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class Announcement extends Model { use HasUuids,SoftDeletes; protected $fillable=['author_id','title','body','audience','starts_at','ends_at','status']; protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime'];} }
