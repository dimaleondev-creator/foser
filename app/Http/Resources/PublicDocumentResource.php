<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'category' => $this->category?->name,
            'author' => $this->author,
            'reference' => $this->reference,
            'document_type' => $this->document_type,
            'year' => $this->year,
            'language' => $this->language,
            'version' => $this->version_label,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'published_at' => $this->published_at?->toISOString(),
            'download_url' => route('documents.download', $this->resource),
            'preview_url' => $this->mime_type === 'application/pdf' ? route('documents.preview', $this->resource) : null,
        ];
    }
}