<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUnit;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __invoke(): View
    {
        $roots = OrganizationUnit::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['responsible', 'childrenRecursive'])
            ->orderBy('sort_order')
            ->orderBy('name_fr')
            ->get();

        return view('public.organization', ['organization' => $roots]);
    }
}
