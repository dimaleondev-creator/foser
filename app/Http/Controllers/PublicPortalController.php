<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\HomeSlider;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\Response;
use App\Models\News;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Media;
use App\Models\MediaAlbum;
use App\Models\ProgramFaq;
use App\Models\SystemSetting;
use App\Contracts\SearchService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Filesystem\FilesystemAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicPortalController extends Controller
{
    public function home(): View
    {
        return view('welcome', [
            'openCalls' => $this->homeCalls(),
            'programs' => $this->rows('programs', ['status' => 'published'], ['name', 'type', 'description'], 4),
            'news' => News::query()->with('category')->where('status', 'published')->where('visibility', 'public')->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at')->limit(3)->get(['category_id', 'title', 'slug', 'excerpt', 'image_path', 'published_at'])->all(),
            'events' => $this->homeEvents(),
            'partners' => $this->homePartners(),
            'testimonials' => $this->rows('testimonials', ['status' => 'published', 'consent_given' => true], ['first_name', 'last_name', 'job_title', 'organization', 'body', 'photo_path', 'rating'], 3),
            'homeSliders' => $this->sliders(),
            'directorPhoto' => SystemSetting::query()->where('key', 'divers')->where('type', 'image')->where('is_public', true)->value('value'),
            'stats' => [
                'students' => $this->count('student_profiles'),
                'applications' => $this->count('applications'),
                'projects' => $this->count('research_projects', ['status' => 'funded']),
                'universities' => $this->count('universities', ['status' => 'active']),
                'programs' => $this->count('programs', ['status' => 'published']),
                'calls' => $this->count('calls', ['status' => 'published']),
            ],
            'cms' => $this->cmsContents(),
        ]);
    }

    public function newsletter(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $subscriber = NewsletterSubscriber::withTrashed()->firstOrNew(['email' => $email]);
        if ($subscriber->exists && $subscriber->status === 'active' && $subscriber->confirmed_at) {
            return redirect()->back()->with('success', 'Si cette adresse peut être inscrite, un message de confirmation lui sera envoyé.');
        }
        $confirmationToken = \Illuminate\Support\Str::random(64);
        $unsubscribeToken = \Illuminate\Support\Str::random(64);
        $subscriber->fill(['name' => $validated['name'] ?? $subscriber->name, 'status' => 'pending', 'source' => 'website', 'confirmation_token_hash' => hash('sha256', $confirmationToken), 'confirmation_expires_at' => now()->addHours(24), 'unsubscribe_token_hash' => hash('sha256', $unsubscribeToken), 'unsubscribe_expires_at' => now()->addDays(365)]);
        if ($subscriber->trashed()) {
            $subscriber->restore();
        }
        $subscriber->save();
        try {
            Mail::raw('Confirmez votre inscription : '.route('newsletter.confirm', $confirmationToken)."\n\nPour vous désinscrire ultérieurement : ".route('newsletter.unsubscribe.token', $unsubscribeToken), fn ($message) => $message->to($subscriber->email)->subject('Confirmation newsletter FOSER'));
        } catch (\Throwable) {
        }

        return redirect()->back()->with('success', 'Si cette adresse peut être inscrite, un message de confirmation lui sera envoyé.');
    }

    public function newsletterUnsubscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $subscriber = NewsletterSubscriber::query()->where('email', strtolower(trim($validated['email'])))->where('status', 'active')->whereNotNull('confirmed_at')->first();
        if ($subscriber) {
            $token = \Illuminate\Support\Str::random(64);
            $subscriber->update(['unsubscribe_token_hash' => hash('sha256', $token), 'unsubscribe_expires_at' => now()->addDay()]);
            try {
                Mail::raw('Confirmez la désinscription de la newsletter : '.route('newsletter.unsubscribe.token', $token), fn ($message) => $message->to($subscriber->email)->subject('Désinscription newsletter FOSER'));
            } catch (\Throwable) {
            }
        }

        return redirect()->back()->with('success', 'Si cette adresse est abonnée, un lien de désinscription lui sera envoyé.');
    }

    public function newsletterConfirm(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($subscriber->confirmation_expires_at?->isFuture(), 410);
        $subscriber->update(['status' => 'active', 'confirmed_at' => now(), 'confirmation_token_hash' => null, 'confirmation_expires_at' => null]);
        return redirect()->route('home')->with('success', 'Votre inscription à la newsletter est confirmée.');
    }

    public function newsletterUnsubscribeToken(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($subscriber->unsubscribe_expires_at?->isFuture(), 410);
        $subscriber->update(['status' => 'inactive', 'unsubscribe_token_hash' => null, 'unsubscribe_expires_at' => null]);
        return redirect()->route('home')->with('success', 'Votre désinscription a bien été enregistrée.');
    }

    public function photos(): View { return view('news.photos', ['albums' => MediaAlbum::query()->where('status', 'published')->latest()->paginate(12)]); }

    public function videos(): View
    {
        $videos = Media::query()->leftJoin('videos', 'videos.media_id', '=', 'media.id')->where('media.media_type', 'video')->where('media.status', 'published')->select('media.*', 'videos.provider', 'videos.duration_seconds')->selectRaw('coalesce(media.external_url, videos.external_url) as external_url')->latest('media.created_at')->paginate(12);
        return view('news.videos', compact('videos'));
    }

    public function pressReleases(Request $request): View
    {
        $releases = \App\Models\PressRelease::query()->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->when($request->query('q'), fn ($query, string $term) => $query->where(fn ($builder) => $builder->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%")))->latest('published_at')->paginate(12)->withQueryString();
        return view('news.press-releases', compact('releases'));
    }

    public function pressRelease(string $release): View
    {
        $release = \App\Models\PressRelease::query()->where('slug', $release)->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->firstOrFail();
        return view('news.press-release', compact('release'));
    }

    public function news(Request $request): View
    {
        $query = News::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($search = $request->query('q')) {
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%"));
        }

        return view('news.index', ['news' => $query->latest('published_at')->paginate(12)->withQueryString()]);
    }

    public function newsArticle(News $article): View
    {
        abort_unless($article->status === 'published' && $article->visibility === 'public' && $article->published_at && $article->published_at <= now(), 404);

        $related = News::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('id', '!=', $article->id)
            ->where(function ($query) use ($article): void {
                $query->where('category_id', $article->category_id)->orWhereNull('category_id');
            })
            ->latest('published_at')->limit(3)->get();

        return view('news.show', compact('article', 'related'));
    }

    public function media(): View
    {
        return view('media.index', [
            'albums' => MediaAlbum::query()->where('status', 'published')->latest()->paginate(12),
            'videos' => Media::query()->leftJoin('videos', 'videos.media_id', '=', 'media.id')->where('media.media_type', 'video')->where('media.status', 'published')->select('media.*', 'videos.provider', 'videos.duration_seconds')->selectRaw('coalesce(media.external_url, videos.external_url) as external_url')->latest('media.created_at')->paginate(12, ['*'], 'videos_page'),
        ]);
    }

    public function album(string $album): View
    {
        $record = MediaAlbum::query()->where('slug', $album)->where('status', 'published')->firstOrFail();
        $media = Media::query()->where('album_id', $record->id)->where('status', 'published')->latest()->paginate(24);

        return view('media.album', ['album' => $record, 'media' => $media]);
    }

    public function documents(Request $request, SearchService $search, ?string $category = null): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'language' => ['nullable', 'string', 'max:10'],
            'type' => ['nullable', 'string', 'max:80'],
        ]);
        $query = Document::query()->with('category')->withCount('downloads')
            ->where('status', 'published')->where('visibility', 'public')
            ->where(function ($builder): void {
                $builder->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
        $query->when($filters['q'] ?? null, function ($builder, string $term) use ($search): void {
            $builder->where(fn ($searchQuery) => $search
                ->search($searchQuery, ['search' => $term], ['title', 'description', 'keywords', 'author', 'reference', 'document_type'])
                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$term}%")));
        });
        $categorySlug = $category ?: ($filters['category'] ?? null);
        $query->when($categorySlug, fn ($builder) => $builder->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug)));
        $query->when($filters['year'] ?? null, fn ($builder, int $year) => $builder->where('year', $year));
        $query->when($filters['language'] ?? null, fn ($builder, string $language) => $builder->where('language', $language));
        $query->when($filters['type'] ?? null, fn ($builder, string $type) => $builder->where('document_type', $type));

        return view('documents.index', [
            'documents' => $query->latest('published_at')->paginate(12)->withQueryString(),
            'categories' => DocumentCategory::query()->orderBy('name')->get(),
            'types' => Document::query()->where('status', 'published')->where('visibility', 'public')->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))->distinct()->orderBy('document_type')->pluck('document_type'),
        ]);
    }

    public function downloadDocument(Document $document): StreamedResponse
    {
        abort_unless($document->status === 'published' && $document->visibility === 'public' && (! $document->published_at || $document->published_at->lessThanOrEqualTo(now())), 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        DB::table('downloads')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'document_id' => $document->id, 'user_id' => Auth::id(), 'ip_address' => request()->ip(), 'downloaded_at' => now()]);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = \Illuminate\Support\Str::slug(pathinfo($document->title, PATHINFO_FILENAME)).'.'.$extension;

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);

        return $disk->download($document->path, $filename, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function previewDocument(Document $document): StreamedResponse
    {
        abort_unless($document->status === 'published' && $document->visibility === 'public' && (! $document->published_at || $document->published_at->lessThanOrEqualTo(now())), 404);
        abort_unless($document->mime_type === 'application/pdf' && Storage::disk($document->disk)->exists($document->path), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);

        return response()->stream(
            fn () => print $disk->get($document->path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.\Illuminate\Support\Str::slug($document->title).'.pdf"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function faq(Request $request): View
    {
        $faqs = ProgramFaq::query()->where('status', 'published')->when($request->string('q')->toString(), fn ($query, string $term) => $query->where(fn ($builder) => $builder->where('question', 'like', "%{$term}%")->orWhere('answer', 'like', "%{$term}%")))->orderBy('sort_order')->orderBy('question')->get();

        return view('faq.index', compact('faqs'));
    }

    public function contactPage(): View
    {
        $settings = SystemSetting::query()->where('is_public', true)->pluck('value', 'key');

        return view('contact.index', compact('settings'));
    }

    private function homePartners(): array
    {
        return $this->partnersAt(['home', 'both']);
    }

    private function homeCalls(): array
    {
        try {
            if (! Schema::hasTable('calls') || ! Schema::hasTable('programs')) {
                return [];
            }

            $columns = ['calls.id', 'calls.title', 'calls.reference', 'calls.opens_at', 'calls.closes_at', 'programs.name as program_name'];
            if (Schema::hasColumn('calls', 'places')) {
                $columns[] = 'calls.places';
            }

            return DB::table('calls')
                ->join('programs', 'programs.id', '=', 'calls.program_id')
                ->whereIn('calls.status', ['published', 'open', 'scheduled'])
                ->whereDate('calls.opens_at', '<=', today())
                ->whereDate('calls.closes_at', '>=', today())
                ->where(function ($query): void {
                    $query->whereNull('calls.published_at')->orWhere('calls.published_at', '<=', now());
                })
                ->orderBy('calls.closes_at')
                ->limit(3)
                ->get($columns)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function homeEvents(): array
    {
        try {
            if (! Schema::hasTable('events')) {
                return [];
            }

            return DB::table('events')
                ->where('status', 'published')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(3)
                ->get(['title', 'slug', 'description', 'venue', 'starts_at'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function partnersAt(array $locations): array
    {
        try {
            if (! Schema::hasTable('partners') || ! Schema::hasColumn('partners', 'display_location')) {
                return [];
            }

            return DB::table('partners')
                ->where('status', 'published')
                ->whereIn('display_location', $locations)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(8)
                ->get(['name', 'slug', 'logo_path', 'description', 'category'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function contact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        ContactMessage::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Votre message a bien été envoyé.');
    }

    public function page(string $page): View
    {
        abort_unless(in_array($page, ['about', 'programs', 'calls', 'news', 'media', 'documents', 'faq', 'contact'], true), 404);

        return view('public.cms-page', ['page' => $page, 'cms' => $this->cmsContents()]);
    }

    public function sitemap(): Response
    {
        $pages = ['', 'about', 'programs', 'calls', 'news', 'media', 'documents', 'faq', 'contact', 'le-foser'];
        $institutionPages = ['historique', 'missions', 'vision', 'valeurs', 'organigramme', 'conseil-administration', 'direction-generale', 'directions', 'documents', 'rapports-annuels'];
        $pages = [...$pages, ...array_map(fn (string $page): string => 'le-foser/'.$page, $institutionPages)];
        $programPages = [
            'programmes',
            'programmes/aides-financieres/conditions', 'programmes/aides-financieres/montants', 'programmes/aides-financieres/procedure', 'programmes/aides-financieres/faq',
            'programmes/prets-etudes/eligibilite', 'programmes/prets-etudes/simulation', 'programmes/prets-etudes/procedure',
            'programmes/recherche/financement', 'programmes/recherche/appels', 'programmes/recherche/projets-finances',
            'programmes/innovation/concours', 'programmes/innovation/incubation', 'programmes/innovation/valorisation',
        ];
        $pages = [...$pages, ...$programPages];
        $urls = collect($pages)->map(fn (string $page): string => '<url><loc>'.e(url('/'.$page)).'</loc></url>')->implode('');

        return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">{$urls}</urlset>", 200, ['Content-Type' => 'application/xml']);
    }

    private function rows(string $table, array $filters, array $columns, int $limit): array
    {
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }

            return DB::table($table)->where($filters)->latest('created_at')->limit($limit)->get($columns)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function count(string $table, array $filters = []): int
    {
        try {
            return Schema::hasTable($table) ? DB::table($table)->where($filters)->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function sliders(): array
    {
        try {
            if (! Schema::hasTable('home_sliders')) {
                return [];
            }

            return HomeSlider::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function cmsContents(): array
    {
        try {
            if (! Schema::hasTable('cms_contents')) return [];

            $locale = app()->getLocale();
            $published = fn (string $contentLocale) => DB::table('cms_contents')->where('locale', $contentLocale)->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->get()->keyBy('content_key');
            return $published(config('app.fallback_locale', 'fr'))->merge($published($locale))->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
