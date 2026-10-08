<?php

namespace App\Http\Controllers;

use App\Services\DirectorDashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DirectorDashboardController extends Controller
{
    public function index(Request $request, DirectorDashboardService $dashboard): View
    {
        $filters = $this->filters($request);

        return view('dashboards.director', [
            'data' => $dashboard->dashboard($filters),
            'user' => $request->user(),
        ]);
    }

    public function exportExcel(Request $request, DirectorDashboardService $dashboard): BinaryFileResponse
    {
        $data = $dashboard->dashboard($this->filters($request));
        $path = tempnam(sys_get_temp_dir(), 'foser-direction-');
        abort_unless($path !== false, 500, 'Impossible de générer le fichier Excel.');

        try {
            $writer = new Writer();
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues(['Section', 'Indicateur', 'Valeur']));
            foreach ($data['summary'] as $key => $value) {
                $writer->addRow(Row::fromValues(['Indicateurs', $this->label($key), $value]));
            }
            foreach ($data['filters'] as $key => $value) {
                $writer->addRow(Row::fromValues(['Filtres appliqués', $key, $value]));
            }
            $writer->addRow(Row::fromValues(['Actualisation', 'Généré le', $data['generated_at']->format('d/m/Y H:i:s')]));
            foreach ($data['charts'] as $section => $rows) {
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues([$this->chartLabel($section), $row['label'], $row['value']]));
                }
            }
            foreach ($data['alerts'] as $alert) {
                $writer->addRow(Row::fromValues(['Alertes décisionnelles', $alert['label'], $alert['count']]));
            }
            $writer->close();
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return response()->download($path, 'dashboard-direction-foser-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request, DirectorDashboardService $dashboard)
    {
        $data = $dashboard->dashboard($this->filters($request));
        $data['summary_labels'] = array_map(fn (string $key): string => $this->label($key), array_keys($data['summary']));
        $data['chart_labels'] = array_map(fn (string $key): string => $this->chartLabel($key), array_keys($data['charts']));

        return Pdf::loadView('dashboards.director-pdf', ['data' => $data])
            ->setPaper('a4', 'landscape')
            ->download('dashboard-direction-foser-'.now()->format('Y-m-d').'.pdf');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
            'university_id' => ['nullable', 'uuid', Rule::exists('universities', 'id')],
            'program_id' => ['nullable', 'uuid', Rule::exists('programs', 'id')],
            'sex' => ['nullable', 'string', 'max:20', Rule::exists('student_profiles', 'sex')],
            'beneficiary_type' => ['nullable', Rule::in(['student', 'researcher'])],
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'completeness_check', 'documents_pending', 'university_review', 'evaluator_assignment', 'evaluation', 'commission_review', 'decision_made', 'result_published', 'awarded', 'finance_pending', 'committed', 'disbursement_pending', 'disbursed', 'rejected', 'cancelled'])],
        ]);
    }

    private function label(string $key): string
    {
        return [
            'students' => 'Étudiants inscrits', 'researchers' => 'Chercheurs', 'universities' => 'Universités',
            'applications' => 'Candidatures', 'amount_requested' => 'Montant demandé',
            'applications_pending' => 'Dossiers en attente', 'applications_processing' => 'Dossiers en traitement',
            'applications_treated' => 'Dossiers traités', 'treatment_rate' => 'Taux de traitement (%)',
            'applications_draft' => 'Candidatures en brouillon', 'applications_submitted' => 'Candidatures soumises',
            'applications_verification' => 'Candidatures en vérification', 'applications_validated' => 'Candidatures validées',
            'applications_rejected' => 'Candidatures rejetées', 'applications_evaluation' => 'Candidatures en évaluation',
            'applications_awarded' => 'Candidatures attribuées', 'applications_completed' => 'Dossiers terminés',
            'amount_committed' => 'Montant engagé', 'amount_awarded' => 'Montant attribué',
            'amount_disbursed' => 'Montant décaissé', 'amount_remaining' => 'Montant restant',
            'payments' => 'Nombre de paiements', 'disbursements' => 'Nombre de décaissements',
            'payments_pending' => 'Paiements en attente', 'payments_completed' => 'Paiements terminés',
            'payments_failed' => 'Paiements échoués', 'amount_paid' => 'Montant payé',
            'projects_submitted' => 'Projets soumis', 'projects_evaluation' => 'Projets en évaluation',
            'projects_funded' => 'Projets financés', 'projects_completed' => 'Projets terminés',
            'research_amount' => 'Montant consacré à la recherche',
        ][$key] ?? Str::headline($key);
    }

    private function chartLabel(string $key): string
    {
        return [
            'monthly_applications' => 'Évolution mensuelle des candidatures',
            'annual_applications' => 'Évolution annuelle des candidatures',
            'by_university' => 'Dossiers par université',
            'by_region' => 'Dossiers par région',
            'by_sex' => 'Répartition par sexe',
            'by_program' => 'Dossiers par programme',
            'by_status' => 'Dossiers par statut',
            'disbursements' => 'Évolution des décaissements',
            'funded_research' => 'Projets financés par programme',
            'financial_comparison' => 'Montants engagés, décaissés et payés',
            'treatment_performance' => 'Performance de traitement',
        ][$key] ?? Str::headline($key);
    }
}
