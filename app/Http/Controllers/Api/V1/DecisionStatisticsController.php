<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DashboardStatisticsService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DecisionStatisticsController
{
    public function overview(Request $request, DashboardStatisticsService $statistics): JsonResponse
    {
        return response()->json(['data' => $statistics->summary($this->filters($request))]);
    }

    public function applications(Request $request, DashboardStatisticsService $statistics): JsonResponse
    {
        $filters = $this->filters($request);
        return response()->json(['data' => ['summary' => $statistics->summary($filters), 'charts' => $statistics->charts($filters, $request->integer('limit', 100))]]);
    }

    public function financial(Request $request, DashboardStatisticsService $statistics): JsonResponse
    {
        $summary = $statistics->summary($this->filters($request));
        return response()->json(['data' => ['committed' => $summary['committed'], 'disbursed' => $summary['disbursed'], 'paid' => $summary['paid'], 'remaining' => $summary['remaining'], 'beneficiaries' => $summary['beneficiaries']]]);
    }

    public function programs(Request $request, DashboardStatisticsService $statistics): JsonResponse { return response()->json(['data' => $statistics->programs($this->filters($request), $request->integer('limit', 100))]); }
    public function universities(Request $request, DashboardStatisticsService $statistics): JsonResponse { return response()->json(['data' => $statistics->universities($this->filters($request), $request->integer('limit', 100))]); }
    public function regions(Request $request, DashboardStatisticsService $statistics): JsonResponse { return response()->json(['data' => $statistics->regions($this->filters($request))]); }
    public function gender(Request $request, DashboardStatisticsService $statistics): JsonResponse { return response()->json(['data' => $statistics->gender($this->filters($request))]); }
    public function trends(Request $request, DashboardStatisticsService $statistics): JsonResponse { return response()->json(['data' => $statistics->trends($this->filters($request))]); }
    public function loans(Request $request, DashboardStatisticsService $statistics): JsonResponse { return response()->json(['data' => $statistics->loanSummary($this->filters($request))]); }

    public function export(Request $request, DashboardStatisticsService $statistics)
    {
        $filters = $this->filters($request);
        $rows = $statistics->programs($filters, 1000);
        app(AuditLogger::class)->record('statistics.exported', self::class, null, [], ['filters' => $filters, 'rows' => count($rows)]);

        return response()->streamDownload(function () use ($rows, $filters): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Rapport statistique FOSER', 'Date de génération', now()->toIso8601String()]);
            fputcsv($handle, ['Filtres', json_encode($filters, JSON_UNESCAPED_UNICODE)]);
            fputcsv($handle, ['Programme', 'Candidatures', 'Validés', 'Rejetés', 'Taux de réussite']);
            foreach ($rows as $row) fputcsv($handle, [$row['label'], $row['applications'], $row['validated'], $row['rejected'], $row['success_rate'].' %']);
            fclose($handle);
        }, 'statistiques-foser-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
    public function research(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        return response()->json(['data' => ['researchers' => \Illuminate\Support\Facades\DB::table('researcher_profiles')->whereIn('status', ['approved', 'active'])->count(), 'projects' => \Illuminate\Support\Facades\DB::table('research_projects')->when($filters['year'] ?? null, fn ($query, $year) => $query->where('year', $year))->count(), 'funded_projects' => \Illuminate\Support\Facades\DB::table('research_projects')->where('status', 'funded')->when($filters['year'] ?? null, fn ($query, $year) => $query->where('year', $year))->count(), 'publications' => \Illuminate\Support\Facades\DB::table('research_publications')->where('status', 'validated')->count()]]);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'region' => ['nullable', 'string', 'max:100'], 'province' => ['nullable', 'string', 'max:100'], 'university_id' => ['nullable', 'uuid'], 'program_id' => ['nullable', 'uuid'], 'sex' => ['nullable', 'string', 'max:20'], 'status' => ['nullable', 'string', 'max:40']]);
    }
}
