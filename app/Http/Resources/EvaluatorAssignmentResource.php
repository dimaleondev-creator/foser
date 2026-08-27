<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluatorAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'status' => $this->status,
            'conflict_declared' => (bool) $this->conflict_declared,
            'submitted_at' => $this->submitted_at,
        ];
    }
}