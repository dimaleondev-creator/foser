<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'call_id' => $this->call_id,
            'program_id' => $this->program_id,
            'status' => $this->status,
            'project_title' => $this->project_title,
            'summary' => $this->summary,
            'description' => $this->description,
            'domain' => $this->domain,
            'objectives' => $this->objectives,
            'methodology' => $this->methodology,
            'calendar' => $this->calendar,
            'budget' => $this->budget,
            'team' => $this->team,
            'submitted_at' => $this->submitted_at,
        ];
    }
}