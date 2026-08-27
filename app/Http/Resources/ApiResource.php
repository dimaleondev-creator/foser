<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    protected array $fields = ['id'];

    public function toArray(Request $request): array
    {
        return collect($this->resource->getAttributes())->only($this->fields)->all();
    }
}
