<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DashboardStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionalStatisticsController extends ApiController
{
    public function __invoke(Request $request, DashboardStatisticsService $statistics): JsonResponse
    {
        $filters = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return response()->json(['data' => $statistics->regions($filters)]);
    }
}