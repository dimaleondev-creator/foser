<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ClaimResource;
use App\Models\Claim;

class ClaimController extends ApiController
{
    protected string $model = Claim::class;
    protected string $resource = ClaimResource::class;
    protected array $searchable = ['reference', 'subject', 'description', 'status'];
    protected array $filterable = ['claimant_id', 'application_id', 'status'];
    protected array $sortable = ['reference', 'resolved_at', 'created_at'];
}
