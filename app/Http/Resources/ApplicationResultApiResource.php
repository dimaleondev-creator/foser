<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResultApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'reference' => $this->application_reference,
            'decision' => $this->decision,
            'score' => $this->score,
            'reason' => $this->reason,
            'published_at' => $this->published_at,
        ];
    }
}