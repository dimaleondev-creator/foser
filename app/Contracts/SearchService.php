<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface SearchService
{
    public function search(Builder $query, array $criteria, array $searchableColumns): Builder;
}
