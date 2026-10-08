<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class News extends Model { use HasUuids,SoftDeletes; protected $table='news'; protected $fillable=['category_id','author_id','media_album_id','title','slug','excerpt','body','image_path','visibility','status','published_at']; protected function casts():array{return ['published_at'=>'datetime'];} public function category(){return $this->belongsTo(NewsCategory::class,'category_id');} public function author(){return $this->belongsTo(User::class,'author_id');} public function album(){return $this->belongsTo(MediaAlbum::class,'media_album_id');} }
