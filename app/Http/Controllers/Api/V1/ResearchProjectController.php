<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ResearchProjectResource;
use App\Models\ResearchProject;

class ResearchProjectController extends ApiController
{
    protected string $model = ResearchProject::class;
    protected string $resource = ResearchProjectResource::class;
    protected array $searchable = ['title', 'reference', 'abstract', 'status'];
    protected array $filterable = ['research_program_id', 'laboratory_id', 'principal_researcher_id', 'status'];
    protected array $sortable = ['title', 'starts_at', 'ends_at', 'created_at'];
}
