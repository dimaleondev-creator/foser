<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SearchService;
use App\Models\Call;
use App\Models\Document;
use App\Models\Event;
use App\Models\News;
use App\Models\Partner;
use App\Models\Program;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'in:news,call,program,document,event,partner,testimonial'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $sources = [
            'news' => [News::class, ['title', 'excerpt', 'body'], fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())],
            'call' => [Call::class, ['title', 'reference', 'description'], fn ($query) => $query->whereIn('status', ['published', 'suspended', 'closed'])],
            'program' => [Program::class, ['name', 'code', 'description'], fn ($query) => $query->where('status', 'published')],
            'document' => [Document::class, ['title', 'description', 'author', 'document_type'], fn ($query) => $query->where('status', 'published')->where('visibility', 'public')],
            'event' => [Event::class, ['title', 'description', 'venue'], fn ($query) => $query->where('status', 'published')],
            'partner' => [Partner::class, ['name', 'description', 'category'], fn ($query) => $query->where('status', 'published')],
            'testimonial' => [Testimonial::class, ['first_name', 'last_name', 'body', 'organization'], fn ($query) => $query->where('status', 'published')->where('consent_given', true)],
        ];
        $selected = $filters['type'] ?? null;
        $results = collect();
        foreach ($sources as $type => [$model, $columns, $visibility]) {
            if ($selected && $selected !== $type) continue;
            $query = $visibility($model::query());
            $results = $results->merge($search->search($query, ['search' => $filters['q']], $columns)->limit(25)->get()->map(fn ($record) => ['type' => $type, 'id' => $record->getKey(), 'title' => $record->title ?? $record->name ?? trim($record->first_name.' '.$record->last_name), 'excerpt' => $record->excerpt ?? $record->description ?? $record->body ?? null]));
        }

        return response()->json(['data' => $results->forPage((int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20))->values(), 'meta' => ['total' => $results->count(), 'page' => (int) ($filters['page'] ?? 1), 'per_page' => (int) ($filters['per_page'] ?? 20)]]);
    }
}
