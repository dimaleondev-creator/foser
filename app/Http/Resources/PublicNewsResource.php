<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicNewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->when($request->route('slug') === $this->slug, $this->body),
            'category' => $this->category?->name,
            'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}