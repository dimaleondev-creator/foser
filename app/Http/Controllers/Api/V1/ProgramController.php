<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Program;
use App\Http\Resources\ProgramResource;

class ProgramController extends ApiController
{
    protected string $model = Program::class;
    protected string $resource = ProgramResource::class;
    protected array $searchable = ['name', 'code', 'description'];
    protected array $filterable = ['type', 'status', 'currency'];
    protected array $sortable = ['name', 'starts_at', 'ends_at', 'created_at'];
}
