<?php

namespace App\Http\Resources;

class ProgramResource extends ApiResource
{
	protected array $fields = ['id', 'name', 'code', 'type', 'description', 'budget', 'currency', 'starts_at', 'ends_at', 'status', 'created_at', 'updated_at'];
}
