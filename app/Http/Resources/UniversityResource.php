<?php

namespace App\Http\Resources;

class UniversityResource extends ApiResource
{
	protected array $fields = ['id', 'name', 'short_name', 'code', 'country', 'city', 'website', 'status', 'created_at', 'updated_at'];
}
