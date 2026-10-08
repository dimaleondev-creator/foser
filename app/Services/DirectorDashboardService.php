<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DirectorDashboardService
{
    private const STATUSES = [
        'draft' => ['draft', 'brouillon'],
        'submitted' => ['submitted', 'soumis'],
        'verification' => ['completeness_check', 'documents_pending', 'university_review', 'verification', 'incomplet', 'recevable', 'under_review'],
        'validated' => ['eligible', 'valide'],
        'rejected' => ['rejected', 'rejete'],
        'evaluation' => ['evaluator_assignment', 'evaluation'],
        'awarded' => ['awarded', 'approuve'],
        'completed' => ['disbursed', 'paye', 'completed', 'archive', 'archived', 'cloture'],
    ];

    private const PENDING_STATUSES = ['draft', 'brouillon', 'submitted', 'soumis', 'documents_pending', 'incomplet'];

    private const PROCESSING_STATUSES = ['completeness_check', 'verification', 'university_review', 'recevable', 'evaluator_assignment', 'evaluation', 'commission_review', 'decision_made', 'result_published', 'finance_pending', 'committed', 'disbursement_pending'];

    public function dashboard(array $filters): array
    {
        $applications = $this->applications($filters);
        $totals = (clone $applications)->selectRaw('count(*) as total, sum(coalesce(applications.budget, 0)) as requested')->first();
        $summary = [
            'students' => $this->students($filters)->count(),
            'researchers' => $this->researchers($filters)->count(),
            'universities' => $this->universitiesQuery($filters)->count(),
            'applications' => (int) ($totals->total ?? 0),
            'amount_requested' => (float) ($totals->requested ?? 0),
        ];

        foreach (self::STATUSES as $key => $statuses) {
            $summary['applications_'.$key] = (clone $applications)->whereIn(DB::raw('coalesce(applications.workflow_status, applications.status)'), $statuses)->count();
        }

        $summary['applications_pending'] = (clone $applications)->whereIn(DB::raw('coalesce(applications.workflow_status, applications.status)'), self::PENDING_STATUSES)->count();
        $summary['applications_processing'] = (clone $applications)->whereIn(DB::raw('coalesce(applications.workflow_status, applications.status)'), self::PROCESSING_STATUSES)->count();
        $summary['applications_treated'] = (clone $applications)->whereIn(DB::raw('coalesce(applications.workflow_status, applications.status)'), [...self::STATUSES['validated'], ...self::STATUSES['rejected'], ...self::STATUSES['awarded'], ...self::STATUSES['completed'], 'accepted', 'approved', 'decision_made', 'result_published', 'committed', 'disbursement_pending'])->count();
        $summary['treatment_rate'] = $summary['applications'] > 0 ? round($summary['applications_treated'] / $summary['applications'] * 100, 1) : 0.0;

        $summary += $this->financialSummary($filters);
        $summary += $this->researchSummary($filters);

        return [
            'summary' => $summary,
            'charts' => $this->charts($filters, $summary),
            'alerts' => $this->alerts($filters),
            'filters' => $filters,
            'options' => $this->filterOptions(),
            'generated_at' => now(),
        ];
    }

    private function applications(array $filters): Builder
    {
        $query = DB::table('applications')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
            ->leftJoin('universities', 'universities.id', '=', 'student_profiles.university_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->whereNull('applications.deleted_at')
            ->whereNull('student_profiles.deleted_at');

        $this->filterApplicationQuery($query, $filters);

        return $query;
    }

    private function filterApplicationQuery(Builder $query, array $filters): void
    {
        $query->when($filters['from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('applications.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('applications.created_at', '<=', $date))
            ->when($filters['region'] ?? null, fn (Builder $builder, string $region) => $builder->where('student_profiles.region', $region))
            ->when($filters['university_id'] ?? null, fn (Builder $builder, string $id) => $builder->where('student_profiles.university_id', $id))
            ->when($filters['program_id'] ?? null, fn (Builder $builder, string $id) => $builder->where('applications.program_id', $id))
            ->when($filters['academic_year'] ?? null, fn (Builder $builder, string $year) => $builder->where('student_profiles.academic_year', $year))
            ->when($filters['year'] ?? null, fn (Builder $builder, int $year) => $builder->whereYear('applications.created_at', $year))
            ->when($filters['sex'] ?? null, fn (Builder $builder, string $sex) => $builder->where('student_profiles.sex', $sex))
            ->when(($filters['beneficiary_type'] ?? null) === 'researcher', fn (Builder $builder) => $builder->whereRaw('1 = 0'))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->whereRaw('coalesce(applications.workflow_status, applications.status) = ?', [$status]));
    }

    private function students(array $filters): Builder
    {
        return DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('users.account_type', 'etudiant')
            ->whereNull('student_profiles.deleted_at')
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('student_profiles.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('student_profiles.created_at', '<=', $date))
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where('student_profiles.region', $region))
            ->when($filters['university_id'] ?? null, fn (Builder $query, string $id) => $query->where('student_profiles.university_id', $id))
            ->when($filters['academic_year'] ?? null, fn (Builder $query, string $year) => $query->where('student_profiles.academic_year', $year))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('student_profiles.created_at', $year))
            ->when($filters['sex'] ?? null, fn (Builder $query, string $sex) => $query->where('student_profiles.sex', $sex))
            ->when(($filters['beneficiary_type'] ?? null) === 'researcher', fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    private function researchers(array $filters): Builder
    {
        return DB::table('researcher_profiles')
            ->join('users', 'users.id', '=', 'researcher_profiles.user_id')
            ->leftJoin('universities', 'universities.id', '=', 'researcher_profiles.university_id')
            ->whereIn('users.account_type', ['chercheur', 'researcher'])
            ->whereIn('researcher_profiles.status', ['approved', 'active'])
            ->whereNull('researcher_profiles.deleted_at')
            ->when(($filters['beneficiary_type'] ?? null) === 'student', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where(fn (Builder $regionQuery) => $regionQuery->where('researcher_profiles.region', $region)->orWhere('universities.region', $region)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('researcher_profiles.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('researcher_profiles.created_at', '<=', $date))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('researcher_profiles.created_at', $year))
            ->when($filters['university_id'] ?? null, fn (Builder $query, string $id) => $query->where('researcher_profiles.university_id', $id))
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where('universities.region', $region));
    }

    private function universitiesQuery(array $filters): Builder
    {
        return DB::table('universities')->where('status', 'active')->whereNull('deleted_at')
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where('region', $region))
            ->when($filters['university_id'] ?? null, fn (Builder $query, string $id) => $query->where('id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('created_at', $year));
    }

    private function financialSummary(array $filters): array
    {
        $commitments = $this->financialCommitments($filters);
        $disbursements = $this->disbursements($filters);
        $payments = $this->payments($filters);
        $committed = (float) (clone $commitments)->whereIn('financial_commitments.status', ['approved', 'valide', 'execute', 'soumis'])->sum('financial_commitments.amount');
        $disbursed = (float) (clone $disbursements)->whereIn('disbursements.status', ['execute', 'disbursed', 'paid'])->sum('disbursements.amount');
        $awards = $this->applications($filters)
            ->join('application_awards', 'application_awards.application_id', '=', 'applications.id')
            ->whereIn('application_awards.status', ['active', 'approved'])
            ->sum('application_awards.amount');

        return [
            'amount_committed' => $committed,
            'amount_awarded' => (float) $awards,
            'amount_disbursed' => $disbursed,
            'amount_remaining' => max(0, $committed - $disbursed),
            'payments' => (clone $payments)->count(),
            'disbursements' => (clone $disbursements)->count(),
            'payments_pending' => (clone $payments)->whereIn('payment_records.status', ['pending', 'processing', 'planned', 'soumis'])->count(),
            'payments_failed' => (clone $payments)->whereIn('payment_records.status', ['failed', 'rejected'])->count(),
            'payments_completed' => (clone $payments)->whereIn('payment_records.status', ['paid', 'execute', 'valide', 'completed'])->count(),
            'amount_paid' => (float) (clone $payments)->whereIn('payment_records.status', ['paid', 'execute', 'valide', 'completed'])->sum('payment_records.amount'),
        ];
    }

    private function financialCommitments(array $filters): Builder
    {
        $query = DB::table('financial_commitments');
        $this->filterFinancialQuery($query, $filters);
        return $query;
    }

    private function disbursements(array $filters): Builder
    {
        $query = DB::table('disbursements')->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id');
        $this->filterFinancialQuery($query, $filters);
        return $query;
    }

    private function payments(array $filters): Builder
    {
        $query = DB::table('payment_records')->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id');
        $this->filterFinancialQuery($query, $filters);
        return $query;
    }

    private function filterFinancialQuery(Builder $query, array $filters): void
    {
        $hasApplicationFilters = filled($filters['region'] ?? null) || filled($filters['university_id'] ?? null) || filled($filters['program_id'] ?? null) || filled($filters['academic_year'] ?? null) || filled($filters['sex'] ?? null) || filled($filters['beneficiary_type'] ?? null);
        if ($hasApplicationFilters) {
            $query->leftJoin('applications as financial_applications', 'financial_applications.id', '=', 'financial_commitments.application_id')
                ->leftJoin('student_profiles as financial_students', 'financial_students.user_id', '=', 'financial_applications.applicant_id')
                ->leftJoin('research_projects as financial_research_projects', 'financial_research_projects.id', '=', 'financial_commitments.research_project_id')
                ->leftJoin('researcher_profiles as financial_researchers', 'financial_researchers.user_id', '=', 'financial_research_projects.principal_researcher_id')
                ->leftJoin('universities as financial_student_universities', 'financial_student_universities.id', '=', 'financial_students.university_id')
                ->leftJoin('universities as financial_research_universities', 'financial_research_universities.id', '=', 'financial_researchers.university_id');
            $query->when($filters['region'] ?? null, fn (Builder $builder, string $region) => $builder->where(function (Builder $builder) use ($region): void {
                     $builder->where('financial_students.region', $region)->orWhere('financial_student_universities.region', $region)->orWhere('financial_researchers.region', $region)->orWhere('financial_research_universities.region', $region);
            }))
                ->when($filters['university_id'] ?? null, fn (Builder $builder, string $id) => $builder->where(function (Builder $builder) use ($id): void {
                    $builder->where('financial_students.university_id', $id)->orWhere('financial_researchers.university_id', $id);
                }))
                ->when($filters['program_id'] ?? null, fn (Builder $builder, string $id) => $builder->where(function (Builder $builder) use ($id): void {
                    $builder->where('financial_applications.program_id', $id)->orWhereExists(fn (Builder $researchPrograms) => $researchPrograms->selectRaw('1')->from('research_programs')->whereColumn('research_programs.id', 'financial_research_projects.research_program_id')->where('research_programs.program_id', $id));
                }))
                ->when($filters['academic_year'] ?? null, fn (Builder $builder, string $year) => $builder->where('financial_students.academic_year', $year));
            $query->when($filters['sex'] ?? null, fn (Builder $builder, string $sex) => $builder->where('financial_students.sex', $sex))
                ->when(($filters['beneficiary_type'] ?? null) === 'student', fn (Builder $builder) => $builder->whereNotNull('financial_commitments.application_id'))
                ->when(($filters['beneficiary_type'] ?? null) === 'researcher', fn (Builder $builder) => $builder->whereNotNull('financial_commitments.research_project_id'));
        }
        $query->when($filters['from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('financial_commitments.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('financial_commitments.created_at', '<=', $date))
            ->when($filters['year'] ?? null, fn (Builder $builder, int $year) => $builder->whereYear('financial_commitments.created_at', $year));
    }

    private function researchSummary(array $filters): array
    {
        $query = $this->researchProjects($filters);
        return [
            'projects_submitted' => (clone $query)->whereIn('research_projects.status', ['submitted', 'verification', 'under_review', 'evaluation', 'accepted', 'funded', 'completed'])->count(),
            'projects_evaluation' => (clone $query)->whereIn('research_projects.status', ['under_review', 'evaluation'])->count(),
            'projects_funded' => (clone $query)->where('research_projects.status', 'funded')->count(),
            'projects_completed' => (clone $query)->whereIn('research_projects.status', ['completed', 'closed', 'archive'])->count(),
            'research_amount' => (float) $this->financialCommitments($filters)->whereNotNull('financial_commitments.research_project_id')->whereIn('financial_commitments.status', ['approved', 'valide', 'execute', 'soumis'])->sum('financial_commitments.amount'),
        ];
    }

    private function researchProjects(array $filters): Builder
    {
        return DB::table('research_projects')->leftJoin('researcher_profiles', 'researcher_profiles.user_id', '=', 'research_projects.principal_researcher_id')
            ->leftJoin('universities', 'universities.id', '=', 'researcher_profiles.university_id')
            ->whereNull('research_projects.deleted_at')
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('research_projects.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('research_projects.created_at', '<=', $date))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('research_projects.created_at', $year))
            ->when(($filters['beneficiary_type'] ?? null) === 'student', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where(fn (Builder $regionQuery) => $regionQuery->where('researcher_profiles.region', $region)->orWhere('universities.region', $region)))
            ->when($filters['university_id'] ?? null, fn (Builder $query, string $id) => $query->where('researcher_profiles.university_id', $id))
            ->when($filters['program_id'] ?? null, fn (Builder $query, string $id) => $query->whereExists(fn (Builder $programQuery) => $programQuery->selectRaw('1')->from('research_programs')->whereColumn('research_programs.id', 'research_projects.research_program_id')->where('research_programs.program_id', $id)));
    }

    private function charts(array $filters, array $summary): array
    {
        return [
            'monthly_applications' => $this->applicationTimeline($filters, 'month', 12),
            'annual_applications' => $this->applicationTimeline($filters, 'year', 6),
            'by_university' => $this->groupedApplications('universities.name', $filters),
            'by_region' => $this->groupedApplications('student_profiles.region', $filters),
            'by_sex' => $this->groupedApplications('student_profiles.sex', $filters),
            'by_program' => $this->groupedApplications('programs.name', $filters),
            'by_status' => $this->groupedApplications('coalesce(applications.workflow_status, applications.status)', $filters),
            'disbursements' => $this->disbursementTimeline($filters),
            'funded_research' => $this->fundedResearch($filters),
            'financial_comparison' => [
                ['label' => 'Engagé', 'value' => (float) $summary['amount_committed']],
                ['label' => 'Décaissé', 'value' => (float) $summary['amount_disbursed']],
                ['label' => 'Payé', 'value' => (float) $summary['amount_paid']],
            ],
            'treatment_performance' => [
                ['label' => 'Dossiers déposés', 'value' => (int) $summary['applications']],
                ['label' => 'Dossiers traités', 'value' => (int) $summary['applications_treated']],
                ['label' => 'Taux (%)', 'value' => (float) $summary['treatment_rate']],
            ],
        ];
    }

    private function applicationTimeline(array $filters, string $period, int $count): array
    {
        $now = CarbonImmutable::now();
        if (filled($filters['year'] ?? null)) {
            $count = $period === 'month' ? 12 : 1;
            $start = CarbonImmutable::create((int) $filters['year'], 1, 1)->startOfYear();
            $end = $start->endOfYear();
        } else {
            $start = $period === 'month' ? $now->startOfMonth()->subMonths($count - 1) : $now->startOfYear()->subYears($count - 1);
            $end = $period === 'month' ? $now->endOfMonth() : $now->endOfYear();
        }
        $group = $this->dateGroup('applications.created_at', $period);
        $rows = $this->applications($filters)->whereBetween('applications.created_at', [$start, $end])
            ->selectRaw("{$group} as bucket, count(*) as total")
            ->groupBy(DB::raw($group))->pluck('total', 'bucket');
        $series = [];
        for ($i = 0; $i < $count; $i++) {
            $date = $period === 'month' ? $start->addMonths($i) : $start->addYears($i);
            $key = $period === 'month' ? $date->format('Y-m') : $date->format('Y');
            $series[] = ['label' => $period === 'month' ? $date->translatedFormat('M Y') : $key, 'value' => (int) ($rows[$key] ?? 0)];
        }
        return $series;
    }

    private function groupedApplications(string $group, array $filters, int $limit = 10): array
    {
        $rows = $this->applications($filters)->whereNotNull(DB::raw($group))
            ->selectRaw("{$group} as label, count(*) as total")
            ->groupBy(DB::raw($group))->orderByDesc('total')->limit($limit)->get()
            ->map(fn (object $row): array => ['label' => (string) ($row->label ?: 'Non renseigné'), 'value' => (int) $row->total])->all();

        if ($group === 'coalesce(applications.workflow_status, applications.status)') {
            $labels = [
                'draft' => 'Brouillon', 'brouillon' => 'Brouillon', 'submitted' => 'Soumise', 'soumis' => 'Soumise',
                'completeness_check' => 'Contrôle de complétude', 'documents_pending' => 'Pièces à compléter',
                'university_review' => 'Vérification universitaire', 'evaluation' => 'Évaluation',
                'commission_review' => 'Commission', 'decision_made' => 'Décision prise',
                'result_published' => 'Résultat publié', 'awarded' => 'Attribuée', 'approuve' => 'Attribuée',
                'committed' => 'Engagée', 'disbursement_pending' => 'Décaissement en attente',
                'disbursed' => 'Terminée', 'rejected' => 'Rejetée', 'rejete' => 'Rejetée',
            ];
            return array_map(fn (array $row): array => [...$row, 'label' => $labels[$row['label']] ?? $row['label']], $rows);
        }

        if ($group === 'student_profiles.sex') {
            return array_map(function (array $row): array {
                $normalized = mb_strtolower(trim($row['label']));
                $label = match ($normalized) {
                    'm', 'male', 'homme', 'masculin' => 'Hommes',
                    'f', 'female', 'femme', 'féminin', 'feminin' => 'Femmes',
                    default => $row['label'],
                };
                return [...$row, 'label' => $label];
            }, $rows);
        }

        return $rows;
    }

    private function disbursementTimeline(array $filters): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $date = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', disbursements.disbursed_at)",
            'mysql', 'mariadb' => "DATE_FORMAT(disbursements.disbursed_at, '%Y-%m')",
            default => "to_char(disbursements.disbursed_at, 'YYYY-MM')",
        };
        $query = $this->disbursements($filters)->whereNotNull('disbursements.disbursed_at')->where('disbursements.disbursed_at', '>=', $start->toDateString());
        $values = $query->selectRaw("{$date} as bucket, sum(disbursements.amount) as total")->groupBy(DB::raw($date))->pluck('total', 'bucket');
        return collect(range(0, 11))->map(function (int $offset) use ($start, $values): array {
            $month = CarbonImmutable::instance($start)->addMonths($offset);
            $key = $month->format('Y-m');
            return ['label' => $month->translatedFormat('M Y'), 'value' => (float) ($values[$key] ?? 0)];
        })->all();
    }

    private function fundedResearch(array $filters): array
    {
        return $this->researchProjects($filters)->where('research_projects.status', 'funded')->whereNotNull('research_projects.research_program_id')
            ->leftJoin('research_programs', 'research_programs.id', '=', 'research_projects.research_program_id')
            ->leftJoin('programs', 'programs.id', '=', 'research_programs.program_id')
            ->selectRaw("coalesce(programs.name, 'Programme non renseigné') as label, count(distinct research_projects.id) as total")
            ->groupBy('programs.name')->orderByDesc('total')->limit(10)->get()
            ->map(fn (object $row): array => ['label' => $row->label, 'value' => (int) $row->total])->all();
    }

    private function alerts(array $filters): array
    {
        $openStatuses = [...self::STATUSES['draft'], ...self::STATUSES['submitted'], ...self::STATUSES['verification'], ...self::STATUSES['evaluation']];
        $blocked = $this->applications($filters)->whereIn(DB::raw('coalesce(applications.workflow_status, applications.status)'), $openStatuses)->where('applications.updated_at', '<', now()->subDays(30))->count();
        $overdue = DB::table('evaluations')->join('applications', 'applications.id', '=', 'evaluations.application_id')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->whereIn('evaluations.status', ['assigned', 'in_progress'])->whereNotNull('evaluations.due_at')->where('evaluations.due_at', '<', now())
            ->when($filters['university_id'] ?? null, fn (Builder $query, string $id) => $query->where('student_profiles.university_id', $id))
            ->when($filters['region'] ?? null, fn (Builder $query, string $region) => $query->where('student_profiles.region', $region))->count();
        $pendingPayments = (clone $this->payments($filters))->whereIn('payment_records.status', ['pending', 'processing', 'planned', 'soumis'])->count();
        $failedPayments = (clone $this->payments($filters))->whereIn('payment_records.status', ['failed', 'rejected'])->count();
        $closingCalls = DB::table('calls')->where('status', 'published')->whereDate('closes_at', '>=', today())->whereDate('closes_at', '<=', today()->addDays(14))->count();
        $intervention = $this->applications($filters)->whereIn(DB::raw('coalesce(applications.workflow_status, applications.status)'), ['documents_pending', 'incomplet', 'complement'])->count()
            + $this->applications($filters)->where('applications.workflow_status', 'evaluation')->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('evaluations')->whereColumn('evaluations.application_id', 'applications.id'))->count();

        return [
            ['key' => 'blocked', 'label' => 'Dossiers bloqués depuis plus de 30 jours', 'count' => (int) $blocked],
            ['key' => 'overdue', 'label' => 'Dossiers en retard d’évaluation', 'count' => (int) $overdue],
            ['key' => 'payments', 'label' => 'Paiements en attente', 'count' => (int) $pendingPayments],
            ['key' => 'failed_payments', 'label' => 'Paiements échoués à contrôler', 'count' => (int) $failedPayments],
            ['key' => 'calls', 'label' => 'Appels proches de la fermeture (14 jours)', 'count' => (int) $closingCalls],
            ['key' => 'intervention', 'label' => 'Dossiers nécessitant une intervention', 'count' => (int) $intervention],
        ];
    }

    private function filterOptions(): array
    {
        $yearGroup = $this->dateGroup('applications.created_at', 'year');
        $regions = collect(DB::table('student_profiles')->whereNotNull('region')->pluck('region'))
            ->merge(DB::table('researcher_profiles')->whereNotNull('region')->pluck('region'))
            ->merge(DB::table('universities')->whereNotNull('region')->pluck('region'))
            ->filter()->unique()->sort()->values()->all();

        return [
            'academic_years' => DB::table('student_profiles')->whereNotNull('academic_year')->distinct()->orderBy('academic_year')->pluck('academic_year')->all(),
            'years' => DB::table('applications')->whereNull('deleted_at')->selectRaw("{$yearGroup} as year")->distinct()->orderByDesc('year')->pluck('year')->filter()->values()->all(),
            'regions' => $regions,
            'universities' => DB::table('universities')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'programs' => DB::table('programs')->whereIn('status', ['published', 'active'])->orderBy('name')->get(['id', 'name']),
            'sexes' => DB::table('student_profiles')->whereNotNull('sex')->distinct()->orderBy('sex')->pluck('sex')->all(),
            'beneficiary_types' => ['student' => 'Étudiants', 'researcher' => 'Chercheurs'],
            'statuses' => [
                'draft' => 'Brouillon', 'submitted' => 'Soumise', 'completeness_check' => 'Contrôle',
                'documents_pending' => 'Pièces à compléter', 'university_review' => 'Vérification université',
                'evaluator_assignment' => 'Affectation des évaluateurs', 'evaluation' => 'Évaluation',
                'commission_review' => 'Commission', 'decision_made' => 'Décision prise',
                'result_published' => 'Résultat publié', 'awarded' => 'Attribuée',
                'finance_pending' => 'Finance en attente', 'committed' => 'Engagée',
                'disbursement_pending' => 'Décaissement en attente', 'disbursed' => 'Décaissée',
                'rejected' => 'Rejetée', 'cancelled' => 'Annulée',
            ],
        ];
    }

    private function dateGroup(string $column, string $period): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => $period === 'month' ? "strftime('%Y-%m', {$column})" : "strftime('%Y', {$column})",
            'mysql', 'mariadb' => $period === 'month' ? "DATE_FORMAT({$column}, '%Y-%m')" : "DATE_FORMAT({$column}, '%Y')",
            default => $period === 'month' ? "to_char({$column}, 'YYYY-MM')" : "to_char({$column}, 'YYYY')",
        };
    }
}
