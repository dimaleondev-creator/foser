<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatisticsService
{
    public function invalidate(): void
    {
        Cache::increment('foser.dashboard.version');
    }

    public function summary(array $filters = []): array
    {
        return Cache::remember($this->key('summary', $filters), now()->addSeconds(60), function () use ($filters): array {
            $profiles = $this->profileQuery($filters);
            $applications = $this->applicationQuery($filters);
            $status = "coalesce(applications.workflow_status, applications.status)";
            $counts = $applications->selectRaw("count(*) as deposited, sum(case when {$status} in ('accepted','eligible','valide','approuve','university_review','evaluation','commission_review','decision_made','result_published','awarded','committed','disbursement_pending','disbursed') then 1 else 0 end) as validated, sum(case when {$status} in ('rejected','rejete') then 1 else 0 end) as rejected, sum(case when {$status} in ('submitted','under_review','verification','soumis','completeness_check','documents_pending','evaluator_assignment','evaluation','commission_review','finance_pending') then 1 else 0 end) as pending")->first();
            $deposited = (int) ($counts->deposited ?? 0);
            $validated = (int) ($counts->validated ?? 0);
            $rejected = (int) ($counts->rejected ?? 0);

            return [
                'students' => (int) $profiles->count(),
                'researchers' => (int) DB::table('researcher_profiles')->whereIn('status', ['approved', 'active'])->count(),
                'universities' => (int) DB::table('universities')->where('status', 'active')->count(),
                'active_programs' => (int) DB::table('programs')->whereIn('status', ['active', 'published'])->count(),
                'beneficiaries' => (int) DB::table('application_awards')->whereIn('status', ['active', 'approved'])->distinct('beneficiary_id')->count('beneficiary_id'),
                'new_students' => (int) $profiles->where('student_profiles.created_at', '>=', now()->subDays(30))->count(),
                'open_calls' => (int) DB::table('calls')->whereIn('status', ['open', 'published'])->whereDate('opens_at', '<=', today())->where(fn ($query) => $query->whereNull('closes_at')->orWhereDate('closes_at', '>=', today()))->count(),
                'applications' => $deposited,
                'validated' => $validated, 'rejected' => $rejected, 'pending' => (int) ($counts->pending ?? 0),
                'committed' => $this->amountQuery('financial_commitments', 'amount', 'valide', $filters),
                'disbursed' => $this->amountQuery('disbursements', 'amount', 'execute', $filters),
                'paid' => $this->paidAmount($filters),
                'funded_projects' => $this->projectCount($filters),
                'treatment_rate' => $deposited > 0 ? round((($validated + $rejected) / $deposited) * 100, 2) : 0,
                'remaining' => max(0, $this->amountQuery('financial_commitments', 'amount', 'valide', $filters) - $this->amountQuery('disbursements', 'amount', 'execute', $filters)),
            ];
        });
    }

    public function charts(array $filters = [], int $limit = 20): array
    {
        $limit = min(max($limit, 1), 100);
        return Cache::remember($this->key('charts', [...$filters, 'limit' => $limit]), now()->addSeconds(60), fn (): array => [
            'monthly' => $this->groupApplications($this->dateGroupExpression('month'), $filters, $limit),
            'annual' => $this->groupApplications($this->dateGroupExpression('year'), $filters, $limit),
            'regions' => $this->groupApplications('student_profiles.region', $filters, $limit),
            'universities' => $this->groupApplications('universities.name', $filters, $limit),
            'programs' => $this->groupApplications('programs.name', $filters, $limit),
            'sex' => $this->groupApplications('student_profiles.sex', $filters, $limit),
            'statuses' => $this->groupApplications('coalesce(applications.workflow_status, applications.status)', $filters, $limit),
            'finance' => $this->financialComparison($filters),
        ]);
    }

    public function regions(array $filters = []): array
    {
        return Cache::remember($this->key('regions', $filters), now()->addSeconds(60), function () use ($filters): array {
            return DB::table('student_profiles')
                ->leftJoin('applications', 'applications.applicant_id', '=', 'student_profiles.user_id')
                ->leftJoin('research_projects', 'research_projects.principal_researcher_id', '=', 'student_profiles.user_id')
                ->whereNotNull('student_profiles.region')
                ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('applications.created_at', $year))
                ->selectRaw("student_profiles.region as region, count(distinct student_profiles.user_id) as beneficiaries, count(distinct applications.id) as applications, count(distinct case when research_projects.status = 'funded' then research_projects.id end) as funded_projects")
                ->groupBy('student_profiles.region')
                ->orderBy('student_profiles.region')
                ->get()
                ->map(fn ($row): array => ['region' => $row->region, 'beneficiaries' => (int) $row->beneficiaries, 'applications' => (int) $row->applications, 'funded_projects' => (int) $row->funded_projects])
                ->all();
        });
    }

    public function universities(array $filters = [], int $limit = 100): array
    {
        return $this->applicationBreakdown('universities.id', 'universities.name', $filters, $limit, true);
    }

    public function programs(array $filters = [], int $limit = 100): array
    {
        return $this->applicationBreakdown('programs.id', 'programs.name', $filters, $limit, false);
    }

    public function gender(array $filters = []): array
    {
        return $this->applicationBreakdown('student_profiles.sex', 'student_profiles.sex', $filters, 20, false);
    }

    public function trends(array $filters = []): array
    {
        $rows = $this->applicationQuery($filters)->selectRaw($this->dateGroupExpression('year').' as label, count(*) as applications')->groupBy(DB::raw($this->dateGroupExpression('year')))->orderBy('label')->get();
        return $rows->map(fn ($row): array => ['label' => $row->label, 'applications' => (int) $row->applications])->all();
    }

    public function loanSummary(array $filters = []): array
    {
        $query = DB::table('study_loan_applications')->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('created_at', $year));
        $total = (int) $query->count();
        $approved = (int) (clone $query)->whereIn('status', ['approved', 'active', 'disbursed'])->count();
        $rejected = (int) (clone $query)->whereIn('status', ['rejected', 'refused'])->count();
        $amount = (float) (clone $query)->whereIn('status', ['approved', 'active', 'disbursed'])->sum('amount');
        $remaining = (float) DB::table('study_loan_installments')->whereIn('status', ['pending', 'late'])->sum('amount');
        return ['requested' => $total, 'approved' => $approved, 'rejected' => $rejected, 'active' => (int) (clone $query)->whereIn('status', ['active', 'disbursed'])->count(), 'amount_approved' => $amount, 'remaining' => $remaining, 'approval_rate' => $total ? round($approved / $total * 100, 2) : 0];
    }

    private function applicationBreakdown(string $groupId, string $groupLabel, array $filters, int $limit, bool $includeRegion): array
    {
        $status = "coalesce(applications.workflow_status, applications.status)";
        return $this->applicationQuery($filters)->whereNotNull(DB::raw($groupId))->selectRaw("{$groupId} as id, {$groupLabel} as label, count(*) as applications, sum(case when {$status} in ('accepted','eligible','valide','approuve','awarded','committed','disbursed') then 1 else 0 end) as validated, sum(case when {$status} in ('rejected','rejete') then 1 else 0 end) as rejected")->groupBy(DB::raw($groupId), DB::raw($groupLabel))->orderByDesc('applications')->limit($limit)->get()->map(fn ($row): array => ['id' => $row->id, 'label' => $row->label ?: 'Non renseigné', 'applications' => (int) $row->applications, 'validated' => (int) $row->validated, 'rejected' => (int) $row->rejected, 'success_rate' => $row->applications ? round(((int) $row->validated / (int) $row->applications) * 100, 2) : 0])->all();
    }

    private function dateGroupExpression(string $period): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => $period === 'month'
                ? "strftime('%Y-%m', applications.created_at)"
                : "strftime('%Y', applications.created_at)",
            'mysql', 'mariadb' => $period === 'month'
                ? "DATE_FORMAT(applications.created_at, '%Y-%m')"
                : "DATE_FORMAT(applications.created_at, '%Y')",
            default => $period === 'month'
                ? "to_char(applications.created_at, 'YYYY-MM')"
                : "to_char(applications.created_at, 'YYYY')",
        };
    }

    private function profileQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('student_profiles');
        $this->applyProfileFilters($query, $filters);
        return $query;
    }

    private function applicationQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('applications')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->leftJoin('universities', 'universities.id', '=', 'student_profiles.university_id')->leftJoin('programs', 'programs.id', '=', 'applications.program_id');
        $this->applyApplicationFilters($query, $filters);
        return $query;
    }

    private function applyProfileFilters(\Illuminate\Database\Query\Builder $query, array $filters): void
    {
        foreach (['region', 'province', 'sex'] as $field) {
            if (filled($filters[$field] ?? null)) $query->where($field, $filters[$field]);
        }
        if (filled($filters['university_id'] ?? null)) $query->where('university_id', $filters['university_id']);
        if (filled($filters['year'] ?? null)) $query->whereYear('student_profiles.created_at', $filters['year']);
    }

    private function applyApplicationFilters(\Illuminate\Database\Query\Builder $query, array $filters): void
    {
        $this->applyProfileFilters($query, $filters);
        if (filled($filters['program_id'] ?? null)) $query->where('applications.program_id', $filters['program_id']);
        if (filled($filters['status'] ?? null)) $query->where('applications.status', $filters['status']);
        if (filled($filters['from'] ?? null)) $query->whereDate('applications.created_at', '>=', $filters['from']);
        if (filled($filters['to'] ?? null)) $query->whereDate('applications.created_at', '<=', $filters['to']);
        if (filled($filters['year'] ?? null)) $query->whereYear('applications.created_at', $filters['year']);
    }

    private function groupApplications(string $group, array $filters, int $limit): array
    {
        return $this->applicationQuery($filters)->whereNotNull(DB::raw($group))->selectRaw("{$group} as label, count(*) as total")->groupBy(DB::raw($group))->orderByDesc('total')->limit($limit)->get()->map(fn ($row): array => ['label' => $row->label ?: 'Non renseigné', 'total' => (int) $row->total])->all();
    }

    private function amountQuery(string $table, string $column, string $status, array $filters): float
    {
        $query = $this->financialQuery($table, $filters)->where("{$table}.status", $status);
        return (float) $query->sum("{$table}.{$column}");
    }

    private function paidAmount(array $filters): float
    {
        return (float) $this->financialQuery('payment_records', $filters)->whereIn('payment_records.status', ['valide', 'execute', 'paid'])->sum('payment_records.amount');
    }

    private function amountGroups(string $table, string $column, array $filters, int $limit): array
    {
        $query = DB::table($table)->where("{$table}.status", $table === 'disbursements' ? 'execute' : 'valide');
        if ($table === 'financial_commitments' && filled($filters['university_id'] ?? null)) $query->join('applications', 'applications.id', '=', 'financial_commitments.application_id')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $filters['university_id']);
        return [['label' => ucfirst($table), 'total' => (float) $query->limit($limit)->sum("{$table}.{$column}")]];
    }

    private function projectCount(array $filters): int
    {
        return (int) DB::table('research_projects')->where('status', 'funded')->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))->when($filters['year'] ?? null, fn ($query, $value) => $query->whereYear('created_at', $value))->count();
    }

    private function financialQuery(string $table, array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);
        if ($table === 'disbursements') $query->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id');
        if ($table === 'payment_records') $query->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id');
        if (filled($filters['university_id'] ?? null) || filled($filters['program_id'] ?? null) || filled($filters['region'] ?? null) || filled($filters['sex'] ?? null)) {
            $query->join('applications', 'applications.id', '=', 'financial_commitments.application_id')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id');
            $this->applyProfileFilters($query, $filters);
            if (filled($filters['program_id'] ?? null)) $query->where('applications.program_id', $filters['program_id']);
        }
        if (filled($filters['year'] ?? null)) $query->whereYear("{$table}.created_at", $filters['year']);
        return $query;
    }

    private function financialComparison(array $filters): array
    {
        return [
            ['label' => 'Engagé', 'total' => $this->amountQuery('financial_commitments', 'amount', 'valide', $filters)],
            ['label' => 'Décaissé', 'total' => $this->amountQuery('disbursements', 'amount', 'execute', $filters)],
            ['label' => 'Payé', 'total' => $this->paidAmount($filters)],
        ];
    }

    private function key(string $type, array $filters): string
    {
        ksort($filters);
        return 'foser.dashboard.'.(Cache::get('foser.dashboard.version', 1)).'.'.$type.'.'.md5(json_encode($filters));
    }
}