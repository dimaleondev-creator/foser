<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResearcherProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'researcher_number' => $this->researcher_number,
            'university_id' => $this->university_id,
            'laboratory_id' => $this->laboratory_id,
            'research_domain' => $this->research_domain,
            'speciality' => $this->speciality,
            'academic_rank' => $this->academic_rank,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'orcid' => $this->orcid,
            'position' => $this->position,
            'years_experience' => $this->years_experience,
            'status' => $this->status,
        ];
    }
}