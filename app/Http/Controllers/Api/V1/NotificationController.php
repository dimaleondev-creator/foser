<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    protected string $model = NotificationDelivery::class;
    protected string $resource = NotificationResource::class;
    protected array $searchable = ['event', 'channel', 'status'];
    protected array $filterable = ['channel', 'status'];
    protected array $sortable = ['sent_at', 'created_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $query->where('user_id', $request->user()->id);
    }
}
