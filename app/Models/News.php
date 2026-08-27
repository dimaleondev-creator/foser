<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class News extends Model { use HasUuids,SoftDeletes; protected $table='news'; protected $fillable=['category_id','author_id','title','slug','excerpt','body','status','published_at']; protected function casts():array{return ['published_at'=>'datetime'];} }
