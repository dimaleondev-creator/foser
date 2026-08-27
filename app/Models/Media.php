<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class Media extends Model { use HasUuids,SoftDeletes; protected $table='media'; protected $fillable=['album_id','uploaded_by','title','media_type','disk','path','mime_type','size','status']; }
