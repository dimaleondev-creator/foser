<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureUniversityResponsible
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (in_array($user->account_type, ['admin', 'super_admin'], true)) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        abort_unless($user->account_type === 'universite', 403);
        abort_unless($user->status === 'active', 403, 'Votre compte est désactivé.');

        $hasUniversity = filled($user->university_id)
            || DB::table('university_users')->where('user_id', $user->id)->exists();
        abort_unless($hasUniversity, 403, 'Votre compte de Responsable universitaire n’est associé à aucune université. Veuillez contacter l’administrateur.');

        return $next($request);
    }
}