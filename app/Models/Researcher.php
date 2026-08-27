<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class Researcher extends Model { use HasUuids,SoftDeletes; protected $table='researcher_profiles'; protected $fillable=['user_id','university_id','laboratory_id','researcher_number','orcid','speciality','research_domain','academic_rank','phone','status','registration_reference','country','region','city','position','years_experience','biography','main_publications','rejection_reason','approved_by','approved_at','rejected_by','rejected_at','suspended_by','suspended_at']; protected function casts():array{return ['approved_at'=>'datetime','rejected_at'=>'datetime','suspended_at'=>'datetime','years_experience'=>'integer'];} }
