<?php

namespace App\Http\Middleware;

use App\Services\RoleDashboardResolver;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user, 403);
        $currentRole = app(RoleDashboardResolver::class)->roleFor($user);
        abort_unless(in_array($currentRole, $roles, true), 403);

        if (in_array($currentRole, ['chercheur', 'researcher'], true)) {
            $profileStatus = DB::table('researcher_profiles')->where('user_id', $user->id)->value('status');
            if ($profileStatus === 'pending') {
                return redirect()->route('researcher.pending');
            }
            if ($profileStatus === 'rejected') {
                return redirect()->route('researcher.rejected');
            }
            if ($profileStatus === 'suspended' || in_array($user->status, ['suspended', 'disabled', 'inactive'], true)) {
                return redirect()->route('researcher.suspended');
            }
        }

        return $next($request);
    }
}
