<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisbursementApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commitment_id' => $this->commitment_id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'status' => $this->status,
            'scheduled_for' => $this->scheduled_for,
            'disbursed_at' => $this->disbursed_at,
        ];
    }
}