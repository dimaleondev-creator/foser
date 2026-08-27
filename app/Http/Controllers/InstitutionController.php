<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Download;
use App\Models\InstitutionValue;
use App\Models\OrganizationUnit;
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
        return view('institution.index', ['sections' => self::SECTIONS]);
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
        $record = Document::query()->whereKey($document)->where('status', 'published')->where('visibility', 'public')->firstOrFail();
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
        return Document::query()->where('status', 'published')->where('visibility', 'public')->when($reports, fn ($query) => $query->where('document_type', 'rapport-annuel'))->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')))->when(request('year'), fn ($query, string $year) => $query->where('year', $year))->latest('published_at')->paginate(12)->withQueryString();
    }

    private function content(string $section): ?object
    {
        return DB::table('cms_contents')->where('content_key', 'institution.'.$section)->where('locale', app()->getLocale())->where('status', 'published')->first()
            ?: DB::table('cms_contents')->where('content_key', 'institution.'.$section)->where('locale', config('app.fallback_locale', 'fr'))->where('status', 'published')->first();
    }
}