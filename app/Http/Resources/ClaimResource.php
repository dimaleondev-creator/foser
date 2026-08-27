<?php

namespace App\Http\Resources;

class ClaimResource extends ApiResource
{
	protected array $fields = ['id', 'claimant_id', 'application_id', 'reference', 'subject', 'description', 'status', 'assigned_to', 'resolved_at', 'created_at', 'updated_at'];
}
