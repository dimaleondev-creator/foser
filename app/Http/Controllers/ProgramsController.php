<?php

namespace App\Http\Controllers;

use App\Models\ProgramFaq;
use App\Services\StudyLoanCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProgramsController extends Controller
{
    public function index(): View
    {
        return view('programs.index', ['catalog' => $this->catalog()]);
    }

    public function category(string $category)
    {
        $firstSection = array_key_first($this->sections()[$category] ?? []);
        abort_unless($firstSection, 404);

        return redirect()->route('programs.section', [$category, $firstSection]);
    }

    public function simulate(Request $request, StudyLoanCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'grace_period_months' => ['nullable', 'integer', 'min:0', 'max:24'],
        ]);

        return response()->json($calculator->simulate((float) $data['amount'], (int) $data['duration_months'], (float) $data['interest_rate'], (int) ($data['grace_period_months'] ?? 0)));
    }

    public function section(string $category, string $section): View
    {
        abort_unless(isset($this->sections()[$category][$section]), 404);

        $data = match ($category) {
            'aides-financieres' => ['items' => DB::table('financial_aids')->join('programs', 'programs.id', '=', 'financial_aids.program_id')->where('financial_aids.status', 'active')->select('financial_aids.*', 'programs.name as program_name')->orderBy('financial_aids.name')->paginate(12)->withQueryString(), 'faqs' => $this->faqs('aides-financieres')],
            'prets-etudes' => ['items' => DB::table('study_loans')->join('programs', 'programs.id', '=', 'study_loans.program_id')->where('study_loans.status', 'active')->select('study_loans.*', 'programs.name as program_name')->orderBy('study_loans.name')->paginate(12)->withQueryString(), 'faqs' => $this->faqs('prets-etudes')],
            'recherche' => ['items' => $this->researchFunding(), 'projects' => $this->fundedProjects(), 'calls' => $this->calls(), 'faqs' => $this->faqs('recherche')],
            'innovation' => ['items' => $this->innovationPrograms($section), 'faqs' => $this->faqs('innovation')],
        };

        return view('programs.section', ['category' => $category, 'section' => $section, 'data' => $data, 'sectionTitle' => $this->sections()[$category][$section]]);
    }

    public function fundedProject(string $project): View
    {
        $record = DB::table('research_projects')->leftJoin('users', 'users.id', '=', 'research_projects.principal_researcher_id')->leftJoin('researcher_profiles', 'researcher_profiles.user_id', '=', 'users.id')->leftJoin('research_programs', 'research_programs.id', '=', 'research_projects.research_program_id')->leftJoin('universities', 'universities.id', '=', 'researcher_profiles.university_id')->where('research_projects.id', $project)->where('research_projects.status', 'funded')->select('research_projects.*', 'research_programs.research_area', 'users.name as researcher_name', 'universities.name as university_name')->first();
        abort_unless($record, 404);

        return view('programs.project', ['project' => $record]);
    }

    public function researchFundingDetail(string $item): View
    {
        $record = DB::table('research_programs')->join('programs', 'programs.id', '=', 'research_programs.program_id')->where('research_programs.id', $item)->where('research_programs.status', 'active')->select('research_programs.*', 'research_programs.research_area as display_name', 'research_programs.research_area as innovation_area', 'programs.name as program_name')->firstOrFail();

        return view('programs.detail', ['category' => 'recherche', 'sectionTitle' => 'Financement', 'record' => $record]);
    }

    public function innovationDetail(string $section, string $item): View
    {
        $record = DB::table('innovation_programs')->join('programs', 'programs.id', '=', 'innovation_programs.program_id')->where('innovation_programs.id', $item)->where('innovation_programs.status', 'active')->where('innovation_programs.innovation_type', ['concours' => 'contest', 'incubation' => 'incubation', 'valorisation' => 'valorization'][$section] ?? '')->select('innovation_programs.*', 'innovation_programs.innovation_area as display_name', 'programs.name as program_name')->firstOrFail();

        return view('programs.detail', ['category' => 'innovation', 'sectionTitle' => $this->sections()['innovation'][$section], 'record' => $record]);
    }

    public function detail(string $category, string $section, string $item): View
    {
        $record = match ($category) {
            'recherche' => DB::table('research_programs')->join('programs', 'programs.id', '=', 'research_programs.program_id')->where('research_programs.id', $item)->where('research_programs.status', 'active')->select('research_programs.*', 'programs.name as program_name')->first(),
            'innovation' => DB::table('innovation_programs')->join('programs', 'programs.id', '=', 'innovation_programs.program_id')->where('innovation_programs.id', $item)->where('innovation_programs.status', 'active')->where('innovation_programs.innovation_type', ['concours' => 'contest', 'incubation' => 'incubation', 'valorisation' => 'valorization'][$section] ?? '')->select('innovation_programs.*', 'programs.name as program_name')->first(),
            default => null,
        };
        abort_unless($record, 404);

        return view('programs.detail', ['category' => $category, 'sectionTitle' => $this->sections()[$category][$section], 'record' => $record]);
    }

    private function catalog(): array
    {
        return [
            ['slug' => 'aides-financieres', 'title' => 'Aides financières', 'description' => 'Conditions, montants, procédure et réponses aux questions fréquentes.', 'links' => $this->sections()['aides-financieres']],
            ['slug' => 'prets-etudes', 'title' => 'Prêts d’études', 'description' => 'Éligibilité, simulation et procédure de demande.', 'links' => $this->sections()['prets-etudes']],
            ['slug' => 'recherche', 'title' => 'Recherche', 'description' => 'Financement, appels à projets et projets financés.', 'links' => $this->sections()['recherche']],
            ['slug' => 'innovation', 'title' => 'Innovation', 'description' => 'Concours, incubation et valorisation.', 'links' => $this->sections()['innovation']],
        ];
    }

    private function sections(): array
    {
        return [
            'aides-financieres' => ['conditions' => 'Conditions', 'montants' => 'Montants', 'procedure' => 'Procédure', 'faq' => 'FAQ'],
            'prets-etudes' => ['eligibilite' => 'Éligibilité', 'simulation' => 'Simulation du prêt', 'procedure' => 'Procédure'],
            'recherche' => ['financement' => 'Financement', 'appels' => 'Appels à projets', 'projets-finances' => 'Projets financés'],
            'innovation' => ['concours' => 'Concours', 'incubation' => 'Incubation', 'valorisation' => 'Valorisation'],
        ];
    }

    private function faqs(string $category)
    {
        return ProgramFaq::query()->where('category', $category)->where('status', 'published')->when(request('q'), function ($query, string $term): void {
            $query->where(function ($query) use ($term): void {
                $query->where('question', 'like', '%'.$term.'%')->orWhere('answer', 'like', '%'.$term.'%');
            });
        })->orderBy('sort_order')->orderBy('question')->get();
    }

    private function calls()
    {
        return DB::table('calls')->join('programs', 'programs.id', '=', 'calls.program_id')->where('calls.status', 'published')->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('calls.title', 'like', '%'.$term.'%')->orWhere('calls.reference', 'like', '%'.$term.'%')))->when(request('domain'), fn ($query, string $domain) => $query->where('calls.domains', 'like', '%'.$domain.'%'))->when(request('year'), fn ($query, string $year) => $query->whereYear('calls.opens_at', $year))->select('calls.*', 'programs.name as program_name')->latest('calls.created_at')->paginate(12)->withQueryString();
    }

    private function researchFunding()
    {
        return DB::table('research_programs')->join('programs', 'programs.id', '=', 'research_programs.program_id')->where('research_programs.status', 'active')->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('research_programs.research_area', 'like', '%'.$term.'%')->orWhere('research_programs.description', 'like', '%'.$term.'%')))->when(request('domain'), fn ($query, string $domain) => $query->where('research_programs.research_area', 'like', '%'.$domain.'%'))->when(request('year'), fn ($query, string $year) => $query->whereYear('research_programs.opens_at', $year))->select('research_programs.*', 'programs.name as program_name')->orderBy('research_programs.research_area')->paginate(12)->withQueryString();
    }

    private function innovationPrograms(string $section)
    {
        return DB::table('innovation_programs')->join('programs', 'programs.id', '=', 'innovation_programs.program_id')->where('innovation_programs.status', 'active')->where('innovation_programs.innovation_type', ['concours' => 'contest', 'incubation' => 'incubation', 'valorisation' => 'valorization'][$section])->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('innovation_programs.innovation_area', 'like', '%'.$term.'%')->orWhere('innovation_programs.description', 'like', '%'.$term.'%')))->when(request('year'), fn ($query, string $year) => $query->whereYear('innovation_programs.opens_at', $year))->select('innovation_programs.*', 'programs.name as program_name')->orderBy('innovation_programs.innovation_area')->paginate(12)->withQueryString();
    }

    private function fundedProjects()
    {
        return DB::table('research_projects')->leftJoin('users', 'users.id', '=', 'research_projects.principal_researcher_id')->leftJoin('researcher_profiles', 'researcher_profiles.user_id', '=', 'users.id')->leftJoin('research_programs', 'research_programs.id', '=', 'research_projects.research_program_id')->leftJoin('universities', 'universities.id', '=', 'researcher_profiles.university_id')->where('research_projects.status', 'funded')->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('research_projects.title', 'like', '%'.$term.'%')->orWhere('research_projects.abstract', 'like', '%'.$term.'%')))->when(request('domain'), fn ($query, string $domain) => $query->where(function ($query) use ($domain): void { $query->where('research_projects.domain', 'like', '%'.$domain.'%')->orWhere('research_programs.research_area', 'like', '%'.$domain.'%'); }))->when(request('year'), fn ($query, string $year) => $query->where('research_projects.year', $year))->when(request('university'), fn ($query, string $university) => $query->where('researcher_profiles.university_id', $university))->select('research_projects.*', 'research_programs.research_area', 'users.name as researcher_name', 'universities.name as university_name')->latest('research_projects.updated_at')->paginate(12)->withQueryString();
    }
}