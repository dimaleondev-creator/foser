<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiController
{
    protected string $model;
    protected string $resource;
    protected array $searchable = [];
    protected array $filterable = [];
    protected array $sortable = ['created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'in:asc,desc'],
        ] + collect($this->filterable)->mapWithKeys(fn (string $field) => [$field => ['nullable', 'string', 'max:100']])->all());

        $query = ($this->model)::query();
        $this->applyQuery($query, $validated);
        $paginator = $query->paginate((int) ($validated['per_page'] ?? 20))->withQueryString();

        return response()->json(['data' => $this->resource::collection($paginator->items()), 'meta' => [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
        ]]);
    }

    public function show(string $id): JsonResponse
    {
        $model = ($this->model)::query()->findOrFail($id);
        return response()->json(['data' => new $this->resource($model)]);
    }

    protected function applyQuery(Builder $query, array $filters): void
    {
        if (! empty($filters['search']) && $this->searchable !== []) {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                foreach ($this->searchable as $field) {
                    $searchQuery->orWhere($field, 'like', '%' . $filters['search'] . '%');
                }
            });
        }
        foreach ($this->filterable as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }
        $sort = in_array($filters['sort'] ?? '', $this->sortable, true) ? $filters['sort'] : 'created_at';
        $query->orderBy($sort, $filters['direction'] ?? 'desc');
    }
}
