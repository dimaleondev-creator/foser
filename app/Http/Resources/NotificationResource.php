<?php

namespace App\Http\Resources;

class NotificationResource extends ApiResource
{
	protected array $fields = ['id', 'event', 'channel', 'status', 'attempts', 'sent_at', 'created_at', 'updated_at'];
}
