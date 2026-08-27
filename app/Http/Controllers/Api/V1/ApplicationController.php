<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApplicationResource;
use App\Models\Application;

class ApplicationController extends ApiController
{
    protected string $model = Application::class;
    protected string $resource = ApplicationResource::class;
    protected array $searchable = ['reference', 'status', 'applicant_note'];
    protected array $filterable = ['call_id', 'program_id', 'applicant_id', 'status'];
    protected array $sortable = ['reference', 'submitted_at', 'decided_at', 'created_at'];
}
