<?php

namespace App\Http\Resources;

class ApplicationResource extends ApiResource
{
	protected array $fields = ['id', 'call_id', 'program_id', 'applicant_id', 'reference', 'status', 'submitted_at', 'decided_at', 'applicant_note', 'created_at', 'updated_at'];
}
