<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends ApiController
{
    protected string $model = NotificationDelivery::class;
    protected string $resource = NotificationResource::class;
    protected array $searchable = ['event', 'channel', 'status'];
    protected array $filterable = ['channel', 'status'];
    protected array $sortable = ['sent_at', 'created_at'];

    protected function applyQuery(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        $query->where('user_id', Auth::id());
        parent::applyQuery($query, $filters);
    }
}
