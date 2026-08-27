<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogController
{
    public function events(Request $request): JsonResponse
    {
        $events = Event::query()->published()->orderBy('starts_at')->paginate($this->perPage($request))->through(fn (Event $event): array => [
            'id' => $event->id, 'title' => $event->title, 'slug' => $event->slug,
            'description' => $event->description, 'category' => $event->category,
            'venue' => $event->venue, 'starts_at' => $event->starts_at?->toISOString(),
            'ends_at' => $event->ends_at?->toISOString(), 'image_url' => $event->image_path ? asset('storage/'.$event->image_path) : null,
        ]);
        return response()->json(['data' => $events->items(), 'meta' => $this->meta($events)]);
    }

    public function partners(Request $request): JsonResponse
    {
        $partners = Partner::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->paginate($this->perPage($request))->through(fn (Partner $partner): array => [
            'id' => $partner->id, 'name' => $partner->name, 'slug' => $partner->slug,
            'description' => $partner->description, 'type' => $partner->type, 'category' => $partner->category,
            'website_url' => $partner->website_url, 'logo_url' => $partner->logo_path ? asset('storage/'.$partner->logo_path) : null,
        ]);
        return response()->json(['data' => $partners->items(), 'meta' => $this->meta($partners)]);
    }

    public function testimonials(Request $request): JsonResponse
    {
        $testimonials = Testimonial::query()->where('status', 'published')->where('consent_given', true)->whereNotNull('published_at')->where('published_at', '<=', now())->orderBy('sort_order')->paginate($this->perPage($request))->through(fn (Testimonial $testimonial): array => [
            'id' => $testimonial->id, 'name' => $testimonial->display_name, 'job_title' => $testimonial->job_title,
            'organization' => $testimonial->organization, 'body' => $testimonial->body, 'rating' => $testimonial->rating,
            'photo_url' => $testimonial->photo_path ? asset('storage/'.$testimonial->photo_path) : null,
            'published_at' => $testimonial->published_at?->toISOString(),
        ]);
        return response()->json(['data' => $testimonials->items(), 'meta' => $this->meta($testimonials)]);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 50);
    }

    private function meta($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()];
    }
}
