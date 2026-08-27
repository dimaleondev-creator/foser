<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'service' => config('app.name')]);
    }

    public function ready(): JsonResponse
    {
        $checks = ['database' => false, 'cache' => false];
        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (\Throwable) {
        }
        try {
            $key = 'healthcheck.'.now()->timestamp;
            Cache::put($key, true, 10);
            $checks['cache'] = Cache::get($key) === true;
            Cache::forget($key);
        } catch (\Throwable) {
        }

        $ready = ! in_array(false, $checks, true);
        return response()->json(['status' => $ready ? 'ok' : 'degraded', 'checks' => $checks], $ready ? 200 : 503);
    }

    public function health(): JsonResponse
    {
        $response = $this->ready();
        return response()->json(['status' => $response->getStatusCode() === 200 ? 'ok' : 'degraded', 'checks' => $response->getData(true)['checks'], 'timestamp' => now()->toISOString()], $response->getStatusCode());
    }
}
