<?php

namespace App\Http\Resources;

class DocumentResource extends ApiResource
{
	protected array $fields = ['id', 'category_id', 'uploaded_by', 'title', 'description', 'year', 'author', 'document_type', 'mime_type', 'size', 'language', 'status', 'published_at', 'created_at', 'updated_at'];

	public function toArray(\Illuminate\Http\Request $request): array
	{
		return parent::toArray($request) + ['download_count' => $this->resource->downloads()->count()];
	}
}
