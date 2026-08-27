<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardStatisticsController extends Controller
{
    public function __invoke(Request $request, DashboardStatisticsService $statistics): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'region' => ['nullable', 'string', 'max:100'], 'province' => ['nullable', 'string', 'max:100'],
            'university_id' => ['nullable', 'uuid'], 'program_id' => ['nullable', 'uuid'],
            'sex' => ['nullable', 'string', 'max:20'], 'status' => ['nullable', 'string', 'max:40'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(['data' => ['kpis' => $statistics->summary($filters), 'charts' => $statistics->charts($filters, (int) ($filters['limit'] ?? 20))]]);
    }
}
