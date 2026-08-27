<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Contracts\SearchService;
use Illuminate\Http\JsonResponse;

class DocumentController extends ApiController
{
    protected SearchService $searchService;

    public function __construct(SearchService $search)
    {
        $this->searchService = $search;
    }

    protected string $model = Document::class;
    protected string $resource = DocumentResource::class;
    protected array $searchable = ['title', 'description', 'author', 'document_type', 'language'];
    protected array $filterable = ['category_id', 'year', 'document_type', 'type', 'language', 'status'];
    protected array $sortable = ['title', 'year', 'published_at', 'created_at', 'updated_at'];

    public function show(string $id): JsonResponse
    {
        $document = Document::query()
            ->whereKey($id)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json(['data' => new $this->resource($document)]);
    }

    protected function applyQuery(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        $query->where('status', 'published');
        if (filled($filters['type'] ?? null) && blank($filters['document_type'] ?? null)) {
            $filters['document_type'] = $filters['type'];
        }
        unset($filters['type']);
        $this->searchService->search($query, $filters, $this->searchable);
        parent::applyQuery($query, array_diff_key($filters, ['search' => true]));
    }
}
