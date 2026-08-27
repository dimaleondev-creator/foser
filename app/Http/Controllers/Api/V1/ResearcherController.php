<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Researcher;
use App\Http\Resources\ResearcherResource;

class ResearcherController extends ApiController
{
    protected string $model = Researcher::class;
    protected string $resource = ResearcherResource::class;
    protected array $searchable = ['researcher_number', 'orcid', 'speciality', 'research_domain', 'academic_rank'];
    protected array $filterable = ['university_id', 'academic_rank'];
    protected array $sortable = ['researcher_number', 'created_at', 'updated_at'];
}
