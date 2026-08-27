<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Call;
use App\Http\Resources\CallResource;

class CallController extends ApiController
{
    protected string $model = Call::class;
    protected string $resource = CallResource::class;
    protected array $searchable = ['title', 'reference', 'description'];
    protected array $filterable = ['program_id', 'status', 'currency'];
    protected array $sortable = ['title', 'opens_at', 'closes_at', 'created_at'];
}
