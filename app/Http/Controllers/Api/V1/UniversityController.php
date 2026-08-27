<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\University;
use App\Http\Resources\UniversityResource;

class UniversityController extends ApiController
{
    protected string $model = University::class;
    protected string $resource = UniversityResource::class;
    protected array $searchable = ['name', 'short_name', 'code', 'city', 'country'];
    protected array $filterable = ['status', 'country'];
    protected array $sortable = ['name', 'created_at', 'updated_at'];
}
