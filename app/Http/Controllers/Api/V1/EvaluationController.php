<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;

class EvaluationController extends ApiController
{
    protected string $model = Evaluation::class;
    protected string $resource = EvaluationResource::class;
    protected array $searchable = ['status', 'comment'];
    protected array $filterable = ['application_id', 'evaluator_id', 'status'];
    protected array $sortable = ['submitted_at', 'created_at', 'updated_at'];
}
