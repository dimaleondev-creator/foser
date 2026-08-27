<?php

namespace App\Http\Resources;

class ResearchProjectResource extends ApiResource
{
	protected array $fields = ['id', 'research_program_id', 'laboratory_id', 'principal_researcher_id', 'title', 'reference', 'abstract', 'budget', 'currency', 'starts_at', 'ends_at', 'status', 'created_at', 'updated_at'];
}
