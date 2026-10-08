<?php

namespace App\Http\Resources;

class EvaluationResource extends ApiResource
{
	protected array $fields = ['id', 'application_id', 'status', 'submitted_at', 'created_at', 'updated_at'];
}
