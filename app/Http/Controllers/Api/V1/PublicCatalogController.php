<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\Document;
use App\Models\News;
use App\Models\Partner;
use App\Models\Testimonial;
use App\Http\Resources\PublicDocumentResource;
use App\Http\Resources\PublicEventResource;
use App\Http\Resources\PublicNewsResource;
use App\Http\Resources\PublicPartnerResource;
use App\Http\Resources\PublicTestimonialResource;
use App\Contracts\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogController
{
    public function news(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $news = News::query()->with('category')->where('status', 'published')->where('visibility', 'public')
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('title', 'like', "%{$term}%")->orWhere('excerpt', 'like', "%{$term}%")))
            ->when($filters['category'] ?? null, fn ($query, string $slug) => $query->whereHas('category', fn ($category) => $category->where('slug', $slug)))
            ->latest('published_at')->paginate($filters['per_page'] ?? 20)->withQueryString();

        return response()->json(['data' => PublicNewsResource::collection($news->items()), 'meta' => $this->meta($news)]);
    }

    public function newsDetail(string $slug): JsonResponse
    {
        $news = News::query()->with('category')->where('slug', $slug)->where('status', 'published')->where('visibility', 'public')
            ->whereNotNull('published_at')->where('published_at', '<=', now())->firstOrFail();

        return response()->json(['data' => new PublicNewsResource($news)]);
    }

    public function documents(Request $request, SearchService $search): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'language' => ['nullable', 'string', 'max:10'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $documents = Document::query()->with('category')->where('status', 'published')->where('visibility', 'public')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($searchQuery) => $search
                ->search($searchQuery, ['search' => $term], ['title', 'description', 'keywords', 'author', 'reference', 'document_type'])
                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$term}%"))))
            ->when($filters['category'] ?? null, fn ($query, string $slug) => $query->whereHas('category', fn ($category) => $category->where('slug', $slug)))
            ->when($filters['year'] ?? null, fn ($query, int $year) => $query->where('year', $year))
            ->when($filters['language'] ?? null, fn ($query, string $language) => $query->where('language', $language))
            ->latest('published_at')->paginate($filters['per_page'] ?? 20)->withQueryString();

        return response()->json(['data' => PublicDocumentResource::collection($documents->items()), 'meta' => $this->meta($documents)]);
    }

    public function events(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:100'], 'category' => ['nullable', 'string', 'max:80'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $events = Event::query()->published()
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhere('venue', 'like', "%{$term}%")))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderBy('starts_at')->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => PublicEventResource::collection($events->items())->resolve($request), 'meta' => $this->meta($events)]);
    }

    public function partners(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:100'], 'category' => ['nullable', 'string', 'max:80'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $partners = Partner::query()->where('status', 'published')
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderBy('sort_order')->orderBy('name')->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => PublicPartnerResource::collection($partners->items())->resolve($request), 'meta' => $this->meta($partners)]);
    }

    public function testimonials(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $testimonials = Testimonial::query()->where('status', 'published')->where('consent_given', true)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('organization', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%")))
            ->orderBy('sort_order')->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => PublicTestimonialResource::collection($testimonials->items())->resolve($request), 'meta' => $this->meta($testimonials)]);
    }

    private function meta($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()];
    }
}
