<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UniversityApplicationApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'call_id' => $this->call_id,
            'program_id' => $this->program_id,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}