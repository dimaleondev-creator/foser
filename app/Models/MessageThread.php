<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class MessageThread extends Model { use HasUuids; protected $table='message_threads'; protected $fillable=['subject','status']; }
