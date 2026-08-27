<?php

namespace App\Http\Resources;

class CallResource extends ApiResource
{
	protected array $fields = ['id', 'program_id', 'category_id', 'title', 'reference', 'description', 'opens_at', 'closes_at', 'places', 'amount', 'currency', 'conditions', 'required_documents', 'eligibility_roles', 'status', 'results_published_at', 'created_at', 'updated_at'];
}
