<?php

namespace App\Http\Resources;

class ResearcherResource extends ApiResource
{
	protected array $fields = ['id', 'user_id', 'university_id', 'laboratory_id', 'researcher_number', 'orcid', 'speciality', 'research_domain', 'academic_rank', 'created_at', 'updated_at'];
}
