<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Contracts\SearchService;
use App\Services\DocumentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DocumentController extends ApiController
{
    protected SearchService $searchService;

    public function __construct(SearchService $search)
    {
        $this->searchService = $search;
    }

    protected string $model = Document::class;
    protected string $resource = DocumentResource::class;
    protected array $searchable = ['title', 'description', 'keywords', 'author', 'document_type', 'language'];
    protected array $filterable = ['category_id', 'year', 'document_type', 'type', 'language', 'status'];
    protected array $sortable = ['title', 'year', 'published_at', 'created_at', 'updated_at'];

    public function show(Request $request, string $id): JsonResponse
    {
        $query = Document::query();
        $this->scopeQuery($query, $request);
        $document = $query
            ->whereKey($id)
            ->firstOrFail();

        return response()->json(['data' => new $this->resource($document)]);
    }

    protected function scopeQuery(Builder $query, Request $request): void
    {
        app(DocumentAccessService::class)->scope($query, $request->user());
    }

    protected function applyQuery(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (filled($filters['type'] ?? null) && blank($filters['document_type'] ?? null)) {
            $filters['document_type'] = $filters['type'];
        }
        unset($filters['type']);
        $term = $filters['search'] ?? null;
        if (filled($term)) {
            $query->where(fn (Builder $searchQuery) => $this->searchService
                ->search($searchQuery, ['search' => $term], $this->searchable)
                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', '%'.$term.'%')));
        }
        parent::applyQuery($query, array_diff_key($filters, ['search' => true]));
    }
}
