<?php

namespace App\Services\Search;

use App\Contracts\SearchService;
use Illuminate\Database\Eloquent\Builder;

class PostgresSearchService implements SearchService
{
    public function search(Builder $query, array $criteria, array $searchableColumns): Builder
    {
        if (filled($criteria['search'] ?? null)) {
            $term = '%' . $criteria['search'] . '%';
            $query->where(function (Builder $searchQuery) use ($term, $searchableColumns): void {
                foreach ($searchableColumns as $column) {
                    $searchQuery->orWhere($column, 'like', $term);
                }
            });
        }

        return $query;
    }
}
