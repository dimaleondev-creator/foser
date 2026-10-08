<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationAwardApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'program_id' => $this->program_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'award_date' => $this->award_date,
            'decision_reference' => $this->decision_reference,
            'status' => $this->status,
        ];
    }
}