<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { use HasUuids; public $timestamps=false; protected $table='audit_logs'; protected $fillable=['user_id','event','auditable_type','auditable_id','old_values','new_values','ip_address','user_agent','created_at']; protected function casts():array{return ['old_values'=>'array','new_values'=>'array','created_at'=>'datetime'];} }
