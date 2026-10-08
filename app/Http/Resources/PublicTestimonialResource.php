<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTestimonialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_name,
            'job_title' => $this->job_title,
            'organization' => $this->organization,
            'body' => $this->body,
            'rating' => $this->rating,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}