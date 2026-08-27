<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventPortalController extends Controller
{
    public function index(Request $request): View
    {
        $base = Event::query()->published()->where(function ($query): void {
            $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->subDay());
        });

        $base->when($request->filled('q'), function ($query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($search) => $search->where('title', 'like', $term)->orWhere('description', 'like', $term)->orWhere('venue', 'like', $term));
        });
        $base->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()));

        return view('events.index', [
            'upcoming' => (clone $base)->upcoming()->orderBy('starts_at')->paginate(9, ['*'], 'upcoming_page')->withQueryString(),
            'past' => (clone $base)->past()->latest('ends_at')->paginate(9, ['*'], 'past_page')->withQueryString(),
            'categories' => Event::query()->published()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function show(Event $event): View
    {
        abort_unless($event->status === 'published', 404);

        return view('events.show', compact('event'));
    }
}