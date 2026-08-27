<?php

namespace App\Http\Resources;

class UserResource extends ApiResource
{
    protected array $fields = ['id', 'name', 'email', 'account_type', 'status', 'created_at', 'updated_at'];
}
