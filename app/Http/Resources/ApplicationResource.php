<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ApplicationResource extends ApiResource
{
	protected array $fields = ['id', 'call_id', 'program_id', 'applicant_id', 'reference', 'status', 'submitted_at', 'decided_at', 'applicant_note', 'created_at', 'updated_at'];

	public function toArray(Request $request): array
	{
		if ($request->user()?->hasRole('evaluateur')) {
			return collect($this->resource->getAttributes())->only(['id', 'call_id', 'program_id', 'reference', 'status', 'submitted_at', 'created_at', 'updated_at'])->all();
		}
		if ($request->user()?->hasRole('universite')) {
			return collect($this->resource->getAttributes())->only(['id', 'call_id', 'program_id', 'reference', 'status', 'submitted_at', 'decided_at', 'created_at', 'updated_at'])->all();
		}

		return parent::toArray($request);
	}
}
