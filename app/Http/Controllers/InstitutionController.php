<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Download;
use App\Models\InstitutionValue;
use App\Models\OrganizationUnit;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionController extends Controller
{
    private const SECTIONS = [
        'historique' => 'Historique', 'missions' => 'Missions', 'vision' => 'Vision', 'valeurs' => 'Valeurs',
        'organigramme' => 'Organigramme', 'conseil-administration' => 'Conseil d’administration',
        'direction-generale' => 'Direction générale', 'directions' => 'Directions techniques',
        'documents' => 'Documents institutionnels', 'rapports-annuels' => 'Rapports annuels',
    ];

    public function index(): View
    {
        $publishedContent = DB::table('cms_contents')
            ->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereIn('locale', [app()->getLocale(), config('app.fallback_locale', 'fr')])
            ->whereIn('content_key', ['institution.historique', 'institution.missions', 'institution.vision'])
            ->orderByRaw('case when locale = ? then 0 else 1 end', [app()->getLocale()])
            ->get()->keyBy('content_key');

        return view('institution.index', [
            'sections' => self::SECTIONS,
            'institutionContent' => $publishedContent,
            'values' => InstitutionValue::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'boardMembers' => OrganizationUnit::query()->active()->where('unit_type', 'board')->with('responsible')->orderBy('sort_order')->orderBy('name_fr')->get(),
            'management' => OrganizationUnit::query()->active()->where('unit_type', 'general_management')->with('responsible')->orderBy('sort_order')->first(),
            'organization' => $this->organization(),
            'documents' => Document::query()->with('category')->where('status', 'published')->where('visibility', 'public')->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))->latest('published_at')->limit(6)->get(),
            'directorPhoto' => SystemSetting::query()->where('key', 'divers')->where('type', 'image')->where('is_public', true)->value('value'),
            'stats' => [
                'students' => DB::table('student_profiles')->whereNull('deleted_at')->count(),
                'researchers' => DB::table('researcher_profiles')->whereIn('status', ['approved', 'active'])->whereNull('deleted_at')->count(),
                'projects' => DB::table('research_projects')->where('status', 'funded')->whereNull('deleted_at')->count(),
                'universities' => DB::table('universities')->where('status', 'active')->whereNull('deleted_at')->count(),
                'committed' => (float) DB::table('financial_commitments')->whereIn('status', ['approved', 'valide', 'execute'])->sum('amount'),
            ],
        ]);
    }

    public function section(string $section): View
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        $data = match ($section) {
            'valeurs' => ['values' => InstitutionValue::active()->orderBy('sort_order')->orderBy('name')->get()],
            'organigramme' => ['organization' => $this->organization()],
            'conseil-administration' => ['organization' => $this->units('board')],
            'direction-generale' => ['organization' => $this->units('general_management')],
            'directions' => ['organization' => $this->units('direction')],
            'documents' => ['documents' => $this->documents(false)],
            'rapports-annuels' => ['documents' => $this->documents(true)],
            default => ['content' => $this->content($section)],
        };

        return view('institution.section', ['section' => $section, 'title' => self::SECTIONS[$section], ...$data]);
    }

    public function direction(string $direction): View
    {
        $unit = OrganizationUnit::query()->active()->where('unit_type', 'direction')->whereKey($direction)->with(['responsible', 'childrenRecursive'])->firstOrFail();

        return view('institution.direction', compact('unit'));
    }

    public function download(string $document): StreamedResponse
    {
        $record = Document::query()->whereKey($document)->where('status', 'published')->where('visibility', 'public')->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))->firstOrFail();
        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);
        Download::create(['document_id' => $record->id, 'user_id' => Auth::id(), 'ip_address' => request()->ip(), 'downloaded_at' => now()]);

        return response()->streamDownload(fn () => print Storage::disk($record->disk)->get($record->path), basename($record->title), ['Content-Type' => $record->mime_type ?: 'application/pdf']);
    }

    private function units(string $type)
    {
        return OrganizationUnit::query()->active()->where('unit_type', $type)->with(['responsible', 'childrenRecursive'])->orderBy('sort_order')->orderBy('name_fr')->get();
    }

    private function organization()
    {
        return OrganizationUnit::query()->active()->whereNull('parent_id')->with(['responsible', 'childrenRecursive'])->orderBy('sort_order')->orderBy('name_fr')->get();
    }

    private function documents(bool $reports)
    {
        return Document::query()->where('status', 'published')->where('visibility', 'public')->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))->when($reports, fn ($query) => $query->where('document_type', 'rapport'))->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%')))->when(request('year'), fn ($query, string $year) => $query->where('year', $year))->latest('published_at')->paginate(12)->withQueryString();
    }

    private function content(string $section): ?object
    {
        return DB::table('cms_contents')->where('content_key', 'institution.'.$section)->where('locale', app()->getLocale())->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->first()
            ?: DB::table('cms_contents')->where('content_key', 'institution.'.$section)->where('locale', config('app.fallback_locale', 'fr'))->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->first();
    }
}