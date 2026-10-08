<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UniversityStudentApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'university_id' => $this->university_id,
            'program' => $this->program,
            'academic_year' => $this->academic_year,
            'faculty' => $this->faculty,
            'study_level' => $this->study_level,
            'validation_status' => $this->validation_status,
            'account_status' => $this->account_status,
        ];
    }
}