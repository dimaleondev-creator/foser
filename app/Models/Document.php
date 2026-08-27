<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Document extends Model { use HasUuids, SoftDeletes; protected $fillable=['category_id','uploaded_by','title','description','year','author','document_type','disk','path','mime_type','size','checksum','visibility','language','status','published_at']; protected function casts():array{return ['published_at'=>'datetime','year'=>'integer','size'=>'integer'];} public function uploader(){return $this->belongsTo(User::class,'uploaded_by');} public function category(){return $this->belongsTo(DocumentCategory::class,'category_id');} public function downloads(){return $this->hasMany(Download::class); } }
