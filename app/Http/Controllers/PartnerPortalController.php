<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $query = Partner::query()->where('status', 'published')->where(function ($builder): void {
            $builder->whereNull('starts_at')->orWhere('starts_at', '<=', today());
        })->where(function ($builder): void {
            $builder->whereNull('ends_at')->orWhere('ends_at', '>=', today());
        });
        $query->when($request->filled('q'), function ($builder) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $builder->where(fn ($search) => $search->where('name', 'like', $term)->orWhere('description', 'like', $term)->orWhere('category', 'like', $term));
        });

        return view('partners.index', ['partners' => $query->orderBy('sort_order')->orderBy('name')->paginate(12)->withQueryString()]);
    }

    public function show(Partner $partner): View
    {
        abort_unless($partner->status === 'published', 404);
        return view('partners.show', compact('partner'));
    }
}
